<?php

namespace App\Services;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\ElectionUnavailableException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpThrottleException;
use App\Exceptions\SmsDeliveryException;
use App\Exceptions\VoterNotFoundException;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\OtpChallenge;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class OtpService
{
    public function __construct(private readonly SmsService $sms) {}

    public function request(string $staffId, ?string $ip = null): array
    {
        $voter = $this->findEligibleVoter($staffId);
        $this->assertCanRequest($voter);

        $plain = $this->generateCode();

        $challenge = DB::transaction(function () use ($voter, $plain, $ip) {
            OtpChallenge::query()
                ->where('voter_id', $voter->id)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            return OtpChallenge::query()->create([
                'voter_id' => $voter->id,
                'code_hash' => $this->hash($plain, $voter),
                'expires_at' => now()->addMinutes((int) config('otp.expiry_minutes')),
                'attempts' => 0,
                'requested_ip' => $ip,
            ]);
        });

        try {
            $this->sms->sendOtp($voter->phone, $plain);
        } catch (SmsDeliveryException $e) {
            $challenge->update(['superseded_at' => now()]);
            throw $e;
        }

        AuditLog::record('otp_requested', 'voter', $voter->id, [
            'staff_id' => $voter->staff_id,
        ]);

        return [
            'staff_id' => $voter->staff_id,
            'name' => $voter->name,
            'phone_masked' => $voter->maskedPhone(),
            'expires_in' => (int) config('otp.expiry_minutes') * 60,
            'resend_after' => (int) config('otp.resend_cooldown_seconds'),
        ];
    }

    public function verify(string $staffId, string $otp): Voter
    {
        $voter = $this->findEligibleVoter($staffId);

        return DB::transaction(function () use ($voter, $otp) {
            /** @var OtpChallenge|null $challenge */
            $challenge = OtpChallenge::query()
                ->where('voter_id', $voter->id)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->expires_at->isPast()) {
                throw new InvalidOtpException('That code is invalid or has expired.');
            }

            if ($challenge->attempts >= (int) config('otp.max_attempts')) {
                $challenge->update(['superseded_at' => now()]);
                throw new InvalidOtpException('Too many incorrect attempts. Request a new code.');
            }

            if (! hash_equals($challenge->code_hash, $this->hash($otp, $voter))) {
                $challenge->increment('attempts');
                AuditLog::record('otp_failed', 'voter', $voter->id, [
                    'staff_id' => $voter->staff_id,
                ]);
                throw new InvalidOtpException('That code is invalid or has expired.');
            }

            $challenge->update([
                'consumed_at' => now(),
                'attempts' => $challenge->attempts + 1,
            ]);

            AuditLog::record('otp_verified', 'voter', $voter->id, [
                'staff_id' => $voter->staff_id,
            ]);

            return $voter->fresh();
        });
    }

    private function findEligibleVoter(string $staffId): Voter
    {
        $election = Election::current();

        if (! $election || ! $election->isOpen()) {
            throw new ElectionUnavailableException('Voting is not open.');
        }

        $voter = Voter::query()
            ->where('election_id', $election->id)
            ->where('staff_id', strtoupper(trim($staffId)))
            ->first();

        if (! $voter) {
            throw new VoterNotFoundException('We could not find that Staff ID.');
        }

        if ($voter->hasVoted()) {
            throw new AlreadyVotedException('This Staff ID has already voted.');
        }

        return $voter;
    }

    private function assertCanRequest(Voter $voter): void
    {
        $cooldown = (int) config('otp.resend_cooldown_seconds');
        $latest = OtpChallenge::query()
            ->where('voter_id', $voter->id)
            ->latest('id')
            ->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = $cooldown - $latest->created_at->diffInSeconds(now());
            throw new OtpThrottleException(
                'Please wait before requesting another code.',
                max(1, $wait),
            );
        }

        $window = (int) config('otp.request_window_minutes');
        $max = (int) config('otp.max_requests');
        $count = OtpChallenge::query()
            ->where('voter_id', $voter->id)
            ->where('created_at', '>=', now()->subMinutes($window))
            ->count();

        if ($count >= $max) {
            throw new OtpThrottleException(
                'Too many verification codes requested. Try again later.',
                $window * 60,
            );
        }
    }

    private function generateCode(): string
    {
        $max = (10 ** (int) config('otp.length')) - 1;

        return str_pad((string) random_int(0, $max), (int) config('otp.length'), '0', STR_PAD_LEFT);
    }

    private function hash(string $otp, Voter $voter): string
    {
        return hash_hmac('sha256', $otp.'|'.$voter->id, (string) config('app.key'));
    }
}
