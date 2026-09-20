<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\Voter;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridDeskFlowTest extends TestCase
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

    public function test_paper_choice_prints_sheets_and_closes_online_voting(): void
    {
        $voter = $this->electionWithVoter();
        $this->app->make(OtpService::class)->request($voter->staff_id);

        $this->post('/otp/verify', [
            'staff_id' => $voter->staff_id,
            'otp' => $this->sms->otp,
        ])->assertRedirect(route('voter.method'));

        $this->get('/method')
            ->assertOk()
            ->assertSee('How will you vote?')
            ->assertSee('Print paper ballot')
            ->assertSee('Vote online');

        $this->get('/ballot')->assertRedirect(route('voter.method'));

        $this->post('/method/paper')->assertRedirect(route('voter.paper.print'));

        $this->assertSame('manual', $voter->fresh()->vote_channel);
        $this->assertNotNull($voter->fresh()->voted_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'voter_voted_manual',
            'actor_type' => 'voter',
        ]);

        $this->get('/paper/print')
            ->assertOk()
            ->assertSee('Secret paper ballot')
            ->assertSee('Digital access is now closed')
            ->assertSee('Printing for '.$voter->name);

        $this->postJson('/api/v1/auth/otp/request', ['staff_id' => $voter->staff_id])
            ->assertStatus(403);

        $this->get('/ballot')->assertRedirect(route('voter.enter'));
        $this->postJson('/api/v1/ballot', [
            'selections' => [['position_id' => 1, 'candidate_id' => 1]],
        ])->assertStatus(401);

        $this->post('/paper/done')
            ->assertOk()
            ->assertSee('Digital voting is closed for this Staff ID');

        $this->get('/paper/print')->assertRedirect(route('voter.enter'));
    }

    public function test_online_choice_is_required_before_the_digital_ballot(): void
    {
        $voter = $this->electionWithVoter();
        $this->app->make(OtpService::class)->request($voter->staff_id);

        $this->post('/otp/verify', [
            'staff_id' => $voter->staff_id,
            'otp' => $this->sms->otp,
        ])->assertRedirect(route('voter.method'));

        $this->post('/method/online')->assertRedirect(route('voter.ballot'));
        $this->get('/ballot')->assertOk()->assertSee('Secret digital ballot');
    }

    public function test_print_page_requires_a_just_issued_paper_session(): void
    {
        $this->get('/paper/print')->assertRedirect(route('voter.enter'));
    }

    private function electionWithVoter(): Voter
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

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate A',
            'sort_order' => 1,
        ]);

        return Voter::query()->create([
            'election_id' => $election->id,
            'staff_id' => 'AGH00123',
            'name' => 'Demo Voter',
            'phone' => '0241231234',
        ]);
    }
}
