<?php

namespace App\Http\Controllers\Ec;

use App\Exceptions\ElectionSetupException;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ElectionControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        $election = Election::current();

        if (! $election) {
            return redirect()->route('ec.dashboard')->withErrors(['election' => 'No election is configured.']);
        }

        return view('ec.settings', ['election' => $election]);
    }

    public function update(Request $request, ElectionControlService $control): RedirectResponse
    {
        $election = Election::currentOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date'],
            'unopposed_voting_enabled' => ['sometimes', 'boolean'],
            'unopposed_voting_scope' => ['nullable', 'in:global,per_position'],
            'unopposed_threshold_type' => ['nullable', 'in:percent,count'],
            'unopposed_threshold_value' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'unopposed_fail_outcome' => ['nullable', 'in:open_nominations,other'],
            'unopposed_fail_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['unopposed_voting_enabled'] = $request->boolean('unopposed_voting_enabled');

        try {
            $control->update($election, $validated);
        } catch (ElectionSetupException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('status', 'Election settings saved.');
    }

    public function open(ElectionControlService $control): RedirectResponse
    {
        try {
            $control->open(Election::currentOrFail());
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return redirect()->route('ec.dashboard')->with('status', 'Voting is open.');
    }

    public function close(ElectionControlService $control): RedirectResponse
    {
        try {
            $control->close(Election::currentOrFail());
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return redirect()->route('ec.dashboard')->with('status', 'The election is closed. Results are final for counting.');
    }

    public function restore(ElectionControlService $control): RedirectResponse
    {
        try {
            $control->restoreVotingPeriod(Election::currentOrFail());
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return redirect()->route('ec.dashboard')->with('status', 'Voting is open again. Official results are no longer public.');
    }

    public function setup(ElectionControlService $control): RedirectResponse
    {
        try {
            $control->returnToSetup(Election::currentOrFail());
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['election' => $e->getMessage()]);
        }

        return redirect()->route('ec.ballot')->with('status', 'Returned to setup. Voting is paused.');
    }
}
