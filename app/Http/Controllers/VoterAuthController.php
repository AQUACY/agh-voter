<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\ElectionUnavailableException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpThrottleException;
use App\Exceptions\SmsDeliveryException;
use App\Exceptions\VoterNotFoundException;
use App\Models\Election;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class VoterAuthController extends Controller
{
    public function enter(): View
    {
        return view('voter.enter', [
            'election' => Election::current(),
        ]);
    }

    public function requestOtp(Request $request, OtpService $otp): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'string', 'max:32'],
        ]);

        try {
            $payload = $otp->request($validated['staff_id'], $request->ip());
        } catch (RuntimeException $e) {
            return $this->fail($request, $e);
        }

        $request->session()->put('otp_staff_id', $payload['staff_id']);
        $request->session()->put('otp_staff_name', $payload['name']);
        $request->session()->put('otp_phone_masked', $payload['phone_masked']);

        if ($this->wantsJson($request)) {
            return response()->json($payload);
        }

        return redirect()->route('voter.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp_staff_id')) {
            return redirect()->route('voter.enter');
        }

        return view('voter.verify', [
            'staffId' => $request->session()->get('otp_staff_id'),
            'staffName' => $request->session()->get('otp_staff_name'),
            'phoneMasked' => $request->session()->get('otp_phone_masked'),
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'digits:6'],
        ]);

        try {
            $voter = $otp->verify($validated['staff_id'], $validated['otp']);
        } catch (RuntimeException $e) {
            return $this->fail($request, $e);
        }

        $request->session()->regenerate();
        $request->session()->put('voter_id', $voter->id);
        $request->session()->put('vote_method', 'digital');
        $request->session()->forget(['otp_staff_id', 'otp_staff_name', 'otp_phone_masked']);

        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'staff_id' => $voter->staff_id,
                'name' => $voter->name,
                'phone_masked' => $voter->maskedPhone(),
                'next' => 'ballot',
            ]);
        }

        return redirect()->route('voter.ballot');
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private function fail(Request $request, RuntimeException $e): JsonResponse|RedirectResponse
    {
        $status = match (true) {
            $e instanceof VoterNotFoundException => 404,
            $e instanceof AlreadyVotedException, $e instanceof ElectionUnavailableException => 403,
            $e instanceof OtpThrottleException => 429,
            $e instanceof InvalidOtpException => 422,
            $e instanceof SmsDeliveryException => 502,
            default => 400,
        };

        $payload = ['message' => $e->getMessage()];

        if ($e instanceof OtpThrottleException) {
            $payload['retry_after'] = $e->retryAfterSeconds;
        }

        $field = $e instanceof InvalidOtpException ? 'otp' : 'staff_id';

        if ($this->wantsJson($request)) {
            return response()->json($payload, $status);
        }

        return back()->withInput()->withErrors([$field => $e->getMessage()]);
    }
}
