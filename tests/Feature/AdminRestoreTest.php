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

class AdminRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_restore_voting_after_a_mistaken_close(): void
    {
        [$admin, $election, $position] = $this->setupElection();

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $position->candidates()->first()->id,
            'created_at' => now(),
        ]);

        $election->update([
            'status' => 'closed',
            'closes_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/ec/restore')
            ->assertRedirect(route('ec.dashboard'));

        $election->refresh();

        $this->assertSame('open', $election->status);
        $this->assertNull($election->published_at);
        $this->assertTrue($election->closes_at->isFuture());
        $this->assertTrue($election->isOpen());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'election_voting_restored',
            'actor_type' => 'admin',
        ]);
    }

    public function test_administrator_can_unpublish_and_reopen_voting(): void
    {
        [$admin, $election] = $this->setupElection();

        $election->update([
            'status' => 'closed',
            'published_at' => now(),
            'closes_at' => now(),
        ]);

        $this->get('/results')->assertOk()->assertDontSee('Official results pending');

        $this->actingAs($admin)->post('/ec/restore')->assertRedirect();

        $this->assertTrue($election->fresh()->isOpen());
        $this->assertNull($election->fresh()->published_at);
        $this->get('/results')->assertOk()->assertSee('Official results pending');
    }

    public function test_electoral_commission_cannot_restore_voting(): void
    {
        [, $election] = $this->setupElection();
        $ec = User::factory()->create(['role' => 'ec']);

        $election->update(['status' => 'closed', 'closes_at' => now()]);

        $this->actingAs($ec)
            ->post('/ec/restore')
            ->assertRedirect(route('ec.dashboard'));

        $this->assertSame('closed', $election->fresh()->status);
    }

    public function test_guests_cannot_restore_voting(): void
    {
        [, $election] = $this->setupElection();
        $election->update(['status' => 'closed']);

        $this->post('/ec/restore')->assertRedirect('/ec/login');
        $this->assertSame('closed', $election->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Election, 2: Position}
     */
    private function setupElection(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
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
        Candidate::query()->create(['position_id' => $position->id, 'name' => 'A', 'sort_order' => 1]);
        Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);

        return [$admin, $election, $position];
    }
}
