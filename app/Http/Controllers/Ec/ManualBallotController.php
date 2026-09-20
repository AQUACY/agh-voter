<?php

namespace App\Http\Controllers\Ec;

use App\Exceptions\ElectionSetupException;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ManualBallotService;
use App\Services\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualBallotController extends Controller
{
    public function print(): View|RedirectResponse
    {
        $election = Election::current();

        if (! $election) {
            return redirect()->route('ec.dashboard');
        }

        $election->load('positions.candidates');

        return view('ec.print-ballot', [
            'election' => $election,
            'sheets' => $election->positions->chunk(2)->values(),
        ]);
    }

    public function tally(ResultService $results): View|RedirectResponse
    {
        $election = Election::current();

        if (! $election) {
            return redirect()->route('ec.dashboard');
        }

        return view('ec.tally', [
            'election' => $election->load('positions.candidates'),
            'results' => $results->live($election),
            'tallies' => $election->manualTallies()
                ->where(function ($query) {
                    $query->whereNull('choice')->orWhere('choice', 'yes');
                })
                ->pluck('votes', 'candidate_id'),
            'noTallies' => $election->manualTallies()
                ->where('choice', 'no')
                ->pluck('votes', 'candidate_id'),
        ]);
    }

    public function saveTallies(Request $request, ManualBallotService $manual): RedirectResponse
    {
        $election = Election::currentOrFail();

        $validated = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'no_counts' => ['nullable', 'array'],
            'no_counts.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        try {
            $manual->saveTallies(
                $election,
                $validated['counts'],
                $validated['no_counts'] ?? [],
            );
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return back()->with('status', 'Paper counts saved. They are included in official totals after you publish.');
    }

    public function publish(ManualBallotService $manual): RedirectResponse
    {
        try {
            $manual->publish(Election::currentOrFail());
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return redirect()->route('results.public')->with('status', 'Official results are now public.');
    }
}
