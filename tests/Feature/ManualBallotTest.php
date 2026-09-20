<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManualBallotTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_vote_locks_the_digital_ballot(): void
    {
        [$ec, $election] = $this->setupElection();
        $voter = $election->voters()->first();

        $this->actingAs($ec)
            ->post('/ec/voters/'.$voter->id.'/paper')
            ->assertRedirect();

        $this->assertSame('manual', $voter->fresh()->vote_channel);
        $this->assertNotNull($voter->fresh()->voted_at);

        $this->postJson('/api/v1/auth/otp/request', ['staff_id' => $voter->staff_id])
            ->assertStatus(403);
    }

    public function test_paper_counts_and_publish_are_required_for_public_results(): void
    {
        [$ec, $election, $position] = $this->setupElection();
        $candidate = $position->candidates()->first();

        $this->get('/results')->assertOk()->assertSee('Official results pending');

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'created_at' => now(),
        ]);

        $this->actingAs($ec)->put('/ec/tally', ['counts' => [$candidate->id => 4]])
            ->assertSessionHasErrors();

        $this->actingAs($ec)->post('/ec/close')->assertRedirect();

        $this->actingAs($ec)->put('/ec/tally', ['counts' => [$candidate->id => 4]])->assertRedirect();
        $this->assertDatabaseHas('manual_tallies', [
            'candidate_id' => $candidate->id,
            'choice' => 'yes',
            'votes' => 4,
        ]);

        $this->actingAs($ec)
            ->getJson('/api/v1/admin/results/live')
            ->assertOk()
            ->assertJsonPath('positions.0.candidates.0.manual_votes', 4)
            ->assertJsonPath('positions.0.candidates.0.digital_votes', 1)
            ->assertJsonPath('positions.0.candidates.0.votes', 5);

        $this->actingAs($ec)->post('/ec/publish')->assertRedirect('/results');
        $this->get('/results')->assertOk()->assertSee('Official results')->assertSee($candidate->name);
    }

    public function test_candidate_portrait_can_be_uploaded_after_voting_starts(): void
    {
        [$ec, $election, $position] = $this->setupElection();
        $candidate = $position->candidates()->first();

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'created_at' => now(),
        ]);

        $this->actingAs($ec)->post('/ec/candidates/'.$candidate->id.'/photo', [
            'photo' => UploadedFile::fake()->image('ama.jpg', 240, 240),
        ])->assertRedirect();

        $path = $candidate->fresh()->photo_path;
        $this->assertNotNull($path);
        $this->assertFileExists(public_path($path));
        @unlink(public_path($path));
    }

    /**
     * @return array{0: User, 1: Election, 2: Position}
     */
    private function setupElection(): array
    {
        $ec = User::factory()->create([
            'role' => 'ec',
            'password' => Hash::make('password'),
        ]);
        $election = Election::query()->create([
            'name' => 'Asesewa Government Hospital Welfare Election',
            'status' => 'open',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
        ]);
        $position = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Chairperson',
            'sort_order' => 1,
        ]);
        Candidate::query()->create(['position_id' => $position->id, 'name' => 'Ama Mensah', 'sort_order' => 1]);
        Candidate::query()->create(['position_id' => $position->id, 'name' => 'Kofi Asante', 'sort_order' => 2]);
        Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);

        return [$ec, $election, $position];
    }
}
