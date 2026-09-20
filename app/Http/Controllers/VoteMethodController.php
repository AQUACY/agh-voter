<?php

namespace App\Http\Controllers;

use App\Exceptions\ElectionSetupException;
use App\Models\Election;
use App\Models\Voter;
use App\Services\ManualBallotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoteMethodController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $voter = $this->voter($request);

        if ($voter->hasVoted()) {
            return redirect()->route('voter.enter');
        }

        if ($request->session()->get('vote_method') === 'digital') {
            return redirect()->route('voter.ballot');
        }

        return view('voter.method', ['voter' => $voter]);
    }

    public function online(Request $request): JsonResponse|RedirectResponse
    {
        $voter = $this->voter($request);

        if ($denied = $this->denyIfUnavailable($request, $voter)) {
            return $denied;
        }

        $request->session()->put('vote_method', 'digital');

        if ($this->wantsJson($request)) {
            return response()->json(['ok' => true, 'method' => 'digital']);
        }

        return redirect()->route('voter.ballot');
    }

    public function paper(Request $request, ManualBallotService $manual): JsonResponse|RedirectResponse
    {
        $voter = $this->voter($request);

        if ($denied = $this->denyIfUnavailable($request, $voter)) {
            return $denied;
        }

        try {
            $manual->lockPaperVote($voter, 'voter');
        } catch (ElectionSetupException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return redirect()->route('voter.enter')->withErrors(['staff_id' => $e->getMessage()]);
        }

        $request->session()->forget(['voter_id', 'vote_method']);
        $request->session()->put('paper_print', true);
        $request->session()->put('paper_staff_name', $voter->name);
        $request->session()->put('paper_staff_id', $voter->staff_id);

        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'method' => 'manual',
                'print' => url('/paper/print'),
            ]);
        }

        return redirect()->route('voter.paper.print');
    }

    public function print(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('paper_print')) {
            return redirect()->route('voter.enter');
        }

        $election = Election::current();

        if (! $election) {
            return redirect()->route('voter.enter');
        }

        $election->load('positions.candidates');

        return view('ec.print-ballot', [
            'election' => $election,
            'sheets' => $election->positions->chunk(2)->values(),
            'autoPrint' => true,
            'issuedTo' => $request->session()->get('paper_staff_name'),
            'issuedStaffId' => $request->session()->get('paper_staff_id'),
            'backUrl' => route('voter.paper.done'),
        ]);
    }

    public function done(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('paper_print')) {
            return redirect()->route('voter.enter');
        }

        $name = $request->session()->get('paper_staff_name');
        $staffId = $request->session()->get('paper_staff_id');
        $request->session()->forget(['paper_print', 'paper_staff_name', 'paper_staff_id']);

        return view('voter.paper-done', [
            'staffName' => $name,
            'staffId' => $staffId,
        ]);
    }

    private function voter(Request $request): Voter
    {
        return Voter::query()->with('election')->findOrFail($request->session()->get('voter_id'));
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private function denyIfUnavailable(Request $request, Voter $voter): JsonResponse|RedirectResponse|null
    {
        if ($voter->hasVoted()) {
            if ($this->wantsJson($request)) {
                return response()->json(['message' => 'This Staff ID has already voted.'], 403);
            }

            return redirect()->route('voter.enter')->withErrors(['staff_id' => 'This Staff ID has already voted.']);
        }

        if (! $voter->election->isOpen()) {
            $message = 'Voting is not open.';

            if ($this->wantsJson($request)) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('voter.enter')->withErrors(['staff_id' => $message]);
        }

        return null;
    }
}
