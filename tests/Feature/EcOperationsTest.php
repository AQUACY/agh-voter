<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec_can_open_and_close_an_election(): void
    {
        [$ec, $election] = $this->setupElection('draft');

        $this->actingAs($ec)->post('/ec/open')->assertRedirect();
        $this->assertSame('open', $election->fresh()->status);

        $this->actingAs($ec)->post('/ec/close')->assertRedirect();
        $this->assertSame('closed', $election->fresh()->status);

        $this->postJson('/api/v1/auth/otp/request', ['staff_id' => 'AGH00123'])
            ->assertStatus(403);
    }

    public function test_ballot_cannot_change_after_a_vote(): void
    {
        [$ec, $election, $position] = $this->setupElection('open');

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $position->candidates()->first()->id,
            'created_at' => now(),
        ]);

        $this->actingAs($ec)->post('/ec/positions', ['name' => 'Organizer'])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('positions', ['name' => 'Organizer']);
    }

    public function test_ec_can_add_a_voter_until_the_election_closes(): void
    {
        [$ec, $election] = $this->setupElection('open');

        $this->actingAs($ec)->post('/ec/voters', [
            'staff_id' => 'AGH00999',
            'name' => 'New Staff',
            'phone' => '0249990000',
        ])->assertRedirect();

        $this->assertDatabaseHas('voters', ['staff_id' => 'AGH00999']);

        $election->update(['status' => 'closed']);

        $this->actingAs($ec)->post('/ec/voters', [
            'staff_id' => 'AGH00888',
            'name' => 'Late Staff',
            'phone' => '0248880000',
        ])->assertSessionHasErrors();
    }

    public function test_ec_can_add_a_position_before_any_vote(): void
    {
        [$ec] = $this->setupElection('open');

        $this->actingAs($ec)->post('/ec/positions', ['name' => 'Organizer'])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['name' => 'Organizer']);
    }

    public function test_duplicate_staff_id_is_rejected(): void
    {
        [$ec] = $this->setupElection('open');

        $this->actingAs($ec)->post('/ec/voters', [
            'staff_id' => 'agh00123',
            'name' => 'Duplicate',
            'phone' => '0247778899',
        ])->assertSessionHasErrors('staff_id');
    }

    public function test_voted_voter_cannot_be_removed(): void
    {
        [$ec, $election] = $this->setupElection('open');
        $voter = $election->voters()->first();
        $voter->update(['voted_at' => now()]);

        $this->actingAs($ec)->delete('/ec/voters/'.$voter->id)->assertSessionHasErrors();
        $this->assertDatabaseHas('voters', ['id' => $voter->id]);
    }

    public function test_closed_election_with_votes_cannot_reopen(): void
    {
        [$ec, $election, $position] = $this->setupElection('open');

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $position->candidates()->first()->id,
            'created_at' => now(),
        ]);

        $this->actingAs($ec)->post('/ec/close')->assertRedirect();
        $this->actingAs($ec)->post('/ec/open')->assertSessionHasErrors();
        $this->assertSame('closed', $election->fresh()->status);
    }

    public function test_guests_cannot_open_the_election_or_see_live_results(): void
    {
        $this->setupElection('draft');

        $this->post('/ec/open')->assertRedirect('/ec/login');
        $this->getJson('/api/v1/admin/results/live')->assertUnauthorized();
    }

    public function test_closed_election_shows_official_results_to_ec(): void
    {
        [$ec, $election] = $this->setupElection('open');
        $election->update(['status' => 'closed']);

        $this->actingAs($ec)->get('/ec')
            ->assertOk()
            ->assertSee('Closed counts')
            ->assertSee('Chairperson');
    }

    /**
     * @return array{0: User, 1: Election, 2: Position}
     */
    private function setupElection(string $status): array
    {
        $ec = User::factory()->create(['role' => 'ec']);
        $election = Election::query()->create([
            'name' => 'Asesewa Government Hospital Welfare Election',
            'status' => $status,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
        ]);
        $position = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Chairperson',
            'sort_order' => 1,
        ]);
        Candidate::query()->create(['position_id' => $position->id, 'name' => 'A', 'sort_order' => 1]);
        Candidate::query()->create(['position_id' => $position->id, 'name' => 'B', 'sort_order' => 2]);
        Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);

        return [$ec, $election, $position];
    }
}
