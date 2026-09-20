<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Services\OtpService;
use App\Services\ResultService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnopposedYesNoTest extends TestCase
{
    use RefreshDatabase;

    private object $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new class implements SmsProvider
        {
            public ?string $otp = null;

            public function send(string $phoneNumber, string $message): void
            {
                if (preg_match('/code is (\d{6})/', $message, $matches)) {
                    $this->otp = $matches[1];
                }
            }
        };

        $this->app->instance(SmsProvider::class, $this->sms);
        $this->app->forgetInstance(SmsService::class);
        $this->app->forgetInstance(OtpService::class);
    }

    public function test_global_unopposed_yes_and_no_votes_are_recorded(): void
    {
        [$voter, $election, $candidate] = $this->unopposedElection();

        $this->loginVoter($voter);

        $this->postJson('/api/v1/ballot', [
            'selections' => [[
                'position_id' => $candidate->position_id,
                'candidate_id' => $candidate->id,
                'choice' => 'yes',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('votes', [
            'candidate_id' => $candidate->id,
            'choice' => 'yes',
        ]);

        $voter2 = Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00999',
            'name' => 'Second Voter',
            'phone' => '0249999999',
        ]);

        $this->loginVoter($voter2);

        $this->postJson('/api/v1/ballot', [
            'selections' => [[
                'position_id' => $candidate->position_id,
                'candidate_id' => $candidate->id,
                'choice' => 'no',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('votes', [
            'candidate_id' => $candidate->id,
            'choice' => 'no',
        ]);

        $results = $this->app->make(ResultService::class)->live($election->fresh());
        $office = $results['positions'][0];

        $this->assertTrue($office['yes_no']);
        $this->assertSame(1, $office['unopposed']['yes']);
        $this->assertSame(1, $office['unopposed']['no']);
        $this->assertFalse($office['unopposed']['threshold_met']);
        $this->assertSame('open_nominations', $office['unopposed']['outcome']);
        $this->assertStringContainsString('open for nominations', strtolower($office['unopposed']['message']));
    }

    public function test_unopposed_threshold_percent_elects_candidate(): void
    {
        [$voter, $election, $candidate] = $this->unopposedElection([
            'unopposed_threshold_type' => 'percent',
            'unopposed_threshold_value' => 50,
        ]);

        $this->loginVoter($voter);
        $this->postJson('/api/v1/ballot', [
            'selections' => [[
                'position_id' => $candidate->position_id,
                'candidate_id' => $candidate->id,
                'choice' => 'yes',
            ]],
        ])->assertOk();

        $results = $this->app->make(ResultService::class)->live($election->fresh());
        $this->assertTrue($results['positions'][0]['unopposed']['threshold_met']);
        $this->assertSame('elected', $results['positions'][0]['unopposed']['outcome']);
        $this->assertContains($candidate->name, $results['positions'][0]['leaders']->all());
    }

    public function test_per_position_other_outcome_shows_custom_note(): void
    {
        $election = Election::query()->create([
            'name' => 'Welfare Election',
            'status' => 'open',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
            'unopposed_voting_enabled' => true,
            'unopposed_voting_scope' => 'per_position',
        ]);

        $position = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Chairperson',
            'sort_order' => 1,
            'unopposed_yes_no' => true,
            'unopposed_threshold_type' => 'count',
            'unopposed_threshold_value' => 5,
            'unopposed_fail_outcome' => 'other',
            'unopposed_fail_note' => 'EC will announce next steps on Monday.',
        ]);

        $candidate = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Solo Candidate',
            'sort_order' => 1,
        ]);

        Vote::query()->create([
            'election_id' => $election->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'choice' => 'yes',
            'created_at' => now(),
        ]);

        $results = $this->app->make(ResultService::class)->live($election);
        $this->assertFalse($results['positions'][0]['unopposed']['threshold_met']);
        $this->assertSame('other', $results['positions'][0]['unopposed']['outcome']);
        $this->assertSame('EC will announce next steps on Monday.', $results['positions'][0]['unopposed']['message']);
    }

    public function test_ec_can_save_global_unopposed_settings(): void
    {
        $election = Election::query()->create([
            'name' => 'Welfare Election',
            'status' => 'draft',
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addDays(2),
        ]);

        $ec = User::factory()->create([
            'role' => 'ec',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($ec)->put('/ec/settings', [
            'name' => $election->name,
            'opens_at' => $election->opens_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'closes_at' => $election->closes_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'unopposed_voting_enabled' => '1',
            'unopposed_voting_scope' => 'global',
            'unopposed_threshold_type' => 'percent',
            'unopposed_threshold_value' => 60,
            'unopposed_fail_outcome' => 'other',
            'unopposed_fail_note' => 'Re-run nominations next week.',
        ])->assertRedirect();

        $election->refresh();
        $this->assertTrue($election->unopposed_voting_enabled);
        $this->assertSame('global', $election->unopposed_voting_scope);
        $this->assertSame(60, $election->unopposed_threshold_value);
        $this->assertSame('Re-run nominations next week.', $election->unopposed_fail_note);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Voter, 1: Election, 2: Candidate}
     */
    private function unopposedElection(array $overrides = []): array
    {
        $election = Election::query()->create(array_merge([
            'name' => 'Welfare Election',
            'status' => 'open',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
            'unopposed_voting_enabled' => true,
            'unopposed_voting_scope' => 'global',
            'unopposed_threshold_type' => 'percent',
            'unopposed_threshold_value' => 75,
            'unopposed_fail_outcome' => 'open_nominations',
            'unopposed_fail_note' => null,
        ], $overrides));

        $position = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Chairperson',
            'sort_order' => 1,
        ]);

        $candidate = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Solo Candidate',
            'sort_order' => 1,
        ]);

        $voter = Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);

        return [$voter, $election, $candidate];
    }

    private function loginVoter(Voter $voter): void
    {
        $this->app->make(OtpService::class)->request($voter->staff_id);
        $this->postJson('/api/v1/auth/otp/verify', [
            'staff_id' => $voter->staff_id,
            'otp' => $this->sms->otp,
        ])->assertOk();
        $this->postJson('/api/v1/method/online')->assertOk();
    }
}
