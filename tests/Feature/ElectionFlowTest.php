<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\OtpChallenge;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ElectionFlowTest extends TestCase
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

    public function test_otp_is_hashed_masked_and_never_returned(): void
    {
        $this->electionWithVoter();

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'staff_id' => 'AGH00123',
        ]);

        $response->assertOk()
            ->assertJsonPath('phone_masked', '024****1234')
            ->assertJsonPath('name', 'Demo Voter')
            ->assertJsonMissingPath('otp');

        $this->assertStringNotContainsString('0241231234', $response->getContent());
        $this->assertStringNotContainsString((string) $this->sms->otp, $response->getContent());
        $this->assertNotNull(OtpChallenge::query()->first());
        $this->assertNotEquals($this->sms->otp, OtpChallenge::query()->value('code_hash'));
    }

    public function test_previous_otp_is_invalidated_and_attempts_are_limited(): void
    {
        $this->electionWithVoter();
        $otp = $this->app->make(OtpService::class);

        $otp->request('AGH00123');
        $first = OtpChallenge::query()->latest('id')->first();
        $this->travel(61)->seconds();
        $otp->request('AGH00123');
        $first->refresh();

        $this->assertNotNull($first->superseded_at);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'staff_id' => 'AGH00123',
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'staff_id' => 'AGH00123',
            'otp' => '000000',
        ])->assertStatus(422);
    }

    public function test_one_person_one_vote_and_ballot_secrecy(): void
    {
        [$voter, $election, $selections] = $this->electionWithVoter();
        $this->app->make(OtpService::class)->request($voter->staff_id);

        $this->postJson('/api/v1/auth/otp/verify', [
            'staff_id' => $voter->staff_id,
            'otp' => $this->sms->otp,
        ])->assertOk()->assertJsonPath('next', 'ballot');

        $response = $this->postJson('/api/v1/ballot', ['selections' => $selections])
            ->assertOk()
            ->assertJsonStructure(['ok', 'receipt']);

        $receipt = $voter->fresh()->ballotReceipt;
        $this->assertNotNull($receipt);
        $this->assertSame($receipt->formatted(), $response->json('receipt'));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $receipt->formatted());

        $this->assertNotNull($voter->fresh()->voted_at);
        $this->assertSame('digital', $voter->fresh()->vote_channel);
        $this->assertFalse(Schema::hasColumn('votes', 'voter_id'));
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ballot_cast']);
        $this->assertArrayNotHasKey('voter_id', Vote::query()->first()->getAttributes());

        $ec = User::factory()->create(['role' => 'ec']);
        $this->actingAs($ec)
            ->get('/ec/voters')
            ->assertOk()
            ->assertSee('Digital')
            ->assertSee($receipt->formatted());

        $this->withSession(['voter_id' => $voter->id])
            ->postJson('/api/v1/ballot', ['selections' => $selections])
            ->assertStatus(403);
    }

    public function test_digital_ballot_shows_receipt_on_the_finished_screen(): void
    {
        [$voter, , $selections] = $this->electionWithVoter();
        $this->app->make(OtpService::class)->request($voter->staff_id);

        $this->post('/otp/verify', [
            'staff_id' => $voter->staff_id,
            'otp' => $this->sms->otp,
        ])->assertRedirect(route('voter.ballot'));

        $this->post('/ballot', ['selections' => $selections])
            ->assertRedirect(route('voter.done'));

        $formatted = $voter->fresh()->ballotReceipt->formatted();

        $this->get('/done')
            ->assertOk()
            ->assertSee('Ballot receipt')
            ->assertSee($formatted);
    }

    public function test_live_results_require_ec_authentication(): void
    {
        $this->electionWithVoter();

        $this->getJson('/api/v1/admin/results/live')->assertUnauthorized();

        $ec = User::factory()->create([
            'role' => 'ec',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($ec)
            ->getJson('/api/v1/admin/results/live')
            ->assertOk()
            ->assertJsonStructure(['positions' => [['position', 'candidates' => [['id', 'name', 'votes']]]]]);
    }

    /**
     * @return array{0: Voter, 1: Election, 2: array<int, array{position_id: int, candidate_id: int}>}
     */
    private function electionWithVoter(): array
    {
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

        $candidate = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate A',
            'sort_order' => 1,
        ]);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate B',
            'sort_order' => 2,
        ]);

        $voter = Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);

        return [$voter, $election, [
            ['position_id' => $position->id, 'candidate_id' => $candidate->id],
        ]];
    }
}
