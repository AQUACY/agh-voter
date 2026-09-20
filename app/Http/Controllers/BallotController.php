<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\ElectionUnavailableException;
use App\Exceptions\InvalidBallotException;
use App\Models\Voter;
use App\Services\BallotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BallotController extends Controller
{
    public function show(Request $request, BallotService $ballots): View|RedirectResponse
    {
        $voter = $this->voter($request);

        if ($voter->hasVoted()) {
            return redirect()->route('voter.done');
        }

        if ($request->session()->get('vote_method') !== 'digital') {
            return redirect()->route('voter.method');
        }

        return view('voter.ballot', [
            'voter' => $voter,
            'ballot' => $ballots->payload($voter->election),
        ]);
    }

    public function payload(Request $request, BallotService $ballots): JsonResponse
    {
        $voter = $this->voter($request);

        if ($voter->hasVoted()) {
            return response()->json(['message' => 'This Staff ID has already voted.'], 403);
        }

        if ($request->session()->get('vote_method') !== 'digital') {
            return response()->json([
                'message' => 'Choose paper or online first.',
                'next' => 'method',
            ], 403);
        }

        return response()->json($ballots->payload($voter->election));
    }

    public function store(Request $request, BallotService $ballots): JsonResponse|RedirectResponse
    {
        $voter = $this->voter($request);

        if ($request->session()->get('vote_method') !== 'digital') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Choose paper or online first.',
                    'next' => 'method',
                ], 403);
            }

            return redirect()->route('voter.method');
        }

        $validated = $request->validate([
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.position_id' => ['required', 'integer'],
            'selections.*.candidate_id' => ['nullable', 'integer'],
            'selections.*.choice' => ['nullable', 'in:yes,no'],
        ]);

        try {
            $receipt = $ballots->cast($voter, $validated['selections']);
        } catch (RuntimeException $e) {
            $status = match (true) {
                $e instanceof AlreadyVotedException, $e instanceof ElectionUnavailableException => 403,
                $e instanceof InvalidBallotException => 422,
                default => 400,
            };

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], $status);
            }

            return back()->withErrors(['ballot' => $e->getMessage()]);
        }

        $request->session()->forget(['voter_id', 'vote_method']);
        $request->session()->put('receipt_code', $receipt->receipt_code);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'receipt' => $receipt->receipt_code,
            ]);
        }

        return redirect()->route('voter.done');
    }

    public function done(Request $request): View|RedirectResponse
    {
        $code = $request->session()->get('receipt_code');

        if (! $code) {
            return redirect()->route('voter.enter');
        }

        return view('voter.done', ['receipt' => $code]);
    }

    private function voter(Request $request): Voter
    {
        return Voter::query()->with('election.positions.candidates')->findOrFail($request->session()->get('voter_id'));
    }
}
