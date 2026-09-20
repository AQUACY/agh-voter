<?php

namespace App\Http\Controllers\Ec;

use App\Exceptions\ElectionSetupException;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Services\ElectionControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BallotSetupController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $election = Election::current();

        if (! $election) {
            return redirect()->route('ec.dashboard');
        }

        return view('ec.ballot', [
            'election' => $election->load('positions.candidates'),
        ]);
    }

    public function storePosition(Request $request, ElectionControlService $control): RedirectResponse
    {
        try {
            $election = Election::currentOrFail();
            $control->assertBallotEditable($election);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
            ]);

            Position::query()->create([
                'election_id' => $election->id,
                'name' => $validated['name'],
                'sort_order' => (int) $election->positions()->max('sort_order') + 1,
            ]);
        } catch (ElectionSetupException|RuntimeException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('status', 'Position added.');
    }

    public function destroyPosition(Position $position, ElectionControlService $control): RedirectResponse
    {
        try {
            $control->assertBallotEditable($position->election);
            $position->delete();
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['ballot' => $e->getMessage()]);
        }

        return back()->with('status', 'Position removed.');
    }

    public function storeCandidate(Request $request, Position $position, ElectionControlService $control): RedirectResponse
    {
        try {
            $control->assertBallotEditable($position->election);
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ]);

            $candidate = Candidate::query()->create([
                'position_id' => $position->id,
                'name' => $validated['name'],
                'sort_order' => (int) $position->candidates()->max('sort_order') + 1,
            ]);

            if ($request->hasFile('photo')) {
                $candidate->storeUploadedPhoto($request->file('photo'));
            }
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['ballot' => $e->getMessage()]);
        }

        return back()->with('status', 'Candidate added.');
    }

    public function updatePhoto(Request $request, Candidate $candidate): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $candidate->storeUploadedPhoto($request->file('photo'));

        return back()->with('status', 'Portrait updated for '.$candidate->name.'.');
    }

    public function updateUnopposed(Request $request, Position $position, ElectionControlService $control): RedirectResponse
    {
        try {
            $election = $position->election;
            $control->assertBallotEditable($election);

            if (! $election->unopposed_voting_enabled || $election->unopposed_voting_scope !== 'per_position') {
                throw new ElectionSetupException('Enable unopposed Yes/No in Per position mode from Election settings first.');
            }

            if ($position->candidates()->count() !== 1) {
                throw new ElectionSetupException('Yes/No settings apply only when a position has exactly one candidate.');
            }

            $validated = $request->validate([
                'unopposed_yes_no' => ['sometimes', 'boolean'],
                'unopposed_threshold_type' => ['nullable', 'in:percent,count'],
                'unopposed_threshold_value' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'unopposed_fail_outcome' => ['nullable', 'in:open_nominations,other'],
                'unopposed_fail_note' => ['nullable', 'string', 'max:1000'],
            ]);

            $validated['unopposed_yes_no'] = $request->boolean('unopposed_yes_no');
            $position->update($control->normalizePositionUnopposedSettings($validated));
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['ballot' => $e->getMessage()]);
        }

        return back()->with('status', 'Unopposed Yes/No settings saved for '.$position->name.'.');
    }

    public function destroyCandidate(Candidate $candidate, ElectionControlService $control): RedirectResponse
    {
        try {
            $control->assertBallotEditable($candidate->position->election);
            $candidate->delete();
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['ballot' => $e->getMessage()]);
        }

        return back()->with('status', 'Candidate removed.');
    }
}
