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
            ->assertRedirect(route('voter.paper.print'));

        $this->assertSame('manual', $voter->fresh()->vote_channel);
        $this->assertNotNull($voter->fresh()->voted_at);
        $this->assertDatabaseHas('paper_ballot_serials', [
            'voter_id' => $voter->id,
            'election_id' => $election->id,
        ]);

        $this->postJson('/api/v1/auth/otp/request', ['staff_id' => $voter->staff_id])
            ->assertStatus(403);
    }

    public function test_ec_can_reprint_an_issued_paper_ballot_with_the_same_serial(): void
    {
        [$ec, $election] = $this->setupElection();
        $voter = $election->voters()->first();

        $this->actingAs($ec)->post('/ec/voters/'.$voter->id.'/paper');
        $serial = $voter->fresh()->paperBallotSerial->serial;

        $this->withSession([])->actingAs($ec)
            ->post('/ec/voters/'.$voter->id.'/paper/reprint')
            ->assertRedirect(route('voter.paper.print'));

        $this->assertSame($serial, $voter->fresh()->paperBallotSerial->serial);
        $this->assertDatabaseCount('paper_ballot_serials', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'paper_ballot_reprinted',
            'actor_type' => 'ec',
        ]);

        $this->get('/paper/print')
            ->assertOk()
            ->assertSee($voter->fresh()->paperBallotSerial->formatted())
            ->assertDontSee($voter->staff_id);
    }

    public function test_reprint_is_rejected_for_digital_or_unvoted_staff(): void
    {
        [$ec, $election] = $this->setupElection();
        $voter = $election->voters()->first();

        $this->actingAs($ec)
            ->from('/ec/voters')
            ->post('/ec/voters/'.$voter->id.'/paper/reprint')
            ->assertRedirect('/ec/voters')
            ->assertSessionHasErrors('voters');
    }

    public function test_ec_can_resolve_paper_serial_to_staff_id(): void
    {
        [$ec, $election] = $this->setupElection();
        $voter = $election->voters()->first();

        $this->actingAs($ec)->post('/ec/voters/'.$voter->id.'/paper');

        $serial = $voter->fresh()->paperBallotSerial->serial;
        $formatted = $voter->fresh()->paperBallotSerial->formatted();

        $this->actingAs($ec)
            ->get('/ec/serials?serial='.urlencode($formatted))
            ->assertOk()
            ->assertSee($voter->staff_id)
            ->assertSee($voter->name)
            ->assertDontSee('No paper ballot serial matches');

        $this->actingAs($ec)
            ->get('/ec/serials?serial=NOTAREALSERIAL')
            ->assertOk()
            ->assertSee('No paper ballot serial matches');

        $this->post('/ec/logout');

        $this->get('/ec/serials?serial='.$serial)
            ->assertRedirect(route('ec.login'));
    }

    public function test_printed_paper_ballot_shows_qr_serial_not_staff_id(): void
    {
        [$ec, $election] = $this->setupElection();
        $voter = $election->voters()->first();

        $second = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Secretary',
            'sort_order' => 2,
        ]);
        Candidate::query()->create([
            'position_id' => $second->id,
            'name' => 'Abena Owusu',
            'sort_order' => 1,
        ]);

        $this->actingAs($ec)->post('/ec/voters/'.$voter->id.'/paper');
        $serial = $voter->fresh()->paperBallotSerial->formatted();

        $html = $this->get('/paper/print')
            ->assertOk()
            ->assertSee('Ballot serial')
            ->assertSee($serial)
            ->assertSee('Cut here')
            ->assertSee('Asesewa Government Hospital')
            ->assertSee('Office of the Electoral Commission')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertDontSee($voter->staff_id)
            ->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="serial-code">'.$serial));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'Ballot serial QR for'));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'slip-mast'));
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
