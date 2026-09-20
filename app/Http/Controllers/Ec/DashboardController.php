<?php

namespace App\Http\Controllers\Ec;

use App\Exceptions\ElectionSetupException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Voter;
use App\Services\ElectionControlService;
use App\Services\ManualBallotService;
use App\Services\ResultService;
use App\Support\GhanaPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->canOperateDesk()) {
            return redirect()->route('ec.dashboard');
        }

        return view('ec.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, false)) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Those Electoral Commission credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();

        if (! Auth::user()->canOperateDesk()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Electoral Commission access required.']);
        }

        AuditLog::record('ec_login', Auth::user()->role, Auth::id());

        return redirect()->route('ec.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ec.login');
    }

    public function dashboard(ResultService $results): View
    {
        $election = Election::current();

        return view('ec.dashboard', [
            'election' => $election,
            'results' => $election ? $results->live($election) : ['positions' => [], 'turnout' => ['registered' => 0, 'voted' => 0, 'digital' => 0, 'paper' => 0]],
            'voterCount' => $election ? $election->voters()->count() : 0,
            'votedCount' => $election ? $election->voters()->whereNotNull('voted_at')->count() : 0,
            'digitalCount' => $election ? $election->voters()->where('vote_channel', 'digital')->count() : 0,
            'paperCount' => $election ? $election->voters()->where('vote_channel', 'manual')->count() : 0,
        ]);
    }

    public function voters(Request $request): View
    {
        $election = Election::current();
        $query = $election?->voters()->orderBy('staff_id');

        if ($election && $request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('staff_id', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        return view('ec.voters', [
            'election' => $election,
            'voters' => $query ? $query->paginate(50)->withQueryString() : collect(),
            'q' => (string) $request->string('q'),
        ]);
    }

    public function storeVoter(Request $request, ElectionControlService $control): RedirectResponse
    {
        $election = Election::currentOrFail();

        try {
            $control->assertRegisterEditable($election);
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['staff_id' => $e->getMessage()]);
        }

        $request->merge([
            'staff_id' => strtoupper(trim((string) $request->input('staff_id'))),
        ]);

        $validated = $request->validate([
            'staff_id' => ['required', 'string', 'max:32', Rule::unique('voters', 'staff_id')->where('election_id', $election->id)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            Voter::query()->create([
                'election_id' => $election->id,
                'staff_id' => $validated['staff_id'],
                'name' => $validated['name'],
                'phone' => $validated['phone'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['phone' => $e->getMessage()]);
        }

        AuditLog::record('voter_added', 'ec', $request->user()->id, [
            'staff_id' => strtoupper(trim($validated['staff_id'])),
        ]);

        return back()->with('status', 'Voter added.');
    }

    public function destroyVoter(Voter $voter, ElectionControlService $control): RedirectResponse
    {
        try {
            $control->assertRegisterEditable($voter->election);
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['voters' => $e->getMessage()]);
        }

        if ($voter->hasVoted()) {
            return back()->withErrors(['voters' => 'A voter who has already voted cannot be removed.']);
        }

        $staffId = $voter->staff_id;
        $voter->delete();

        AuditLog::record('voter_removed', 'ec', auth()->id(), [
            'staff_id' => $staffId,
        ]);

        return back()->with('status', 'Voter removed.');
    }

    public function importVoters(Request $request, ElectionControlService $control): RedirectResponse
    {
        $election = Election::current();

        if (! $election) {
            return back()->withErrors(['file' => 'Create an election before importing voters.']);
        }

        try {
            $control->assertRegisterEditable($election);
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $imported = 0;
        $header = true;

        while (($row = fgetcsv($handle)) !== false) {
            if ($header) {
                $header = false;
                if (isset($row[0]) && str_contains(strtolower($row[0]), 'staff')) {
                    continue;
                }
            }

            if (count($row) < 3) {
                continue;
            }

            try {
                Voter::query()->updateOrCreate(
                    [
                        'election_id' => $election->id,
                        'staff_id' => strtoupper(trim($row[0])),
                    ],
                    [
                        'name' => trim($row[1]),
                        'phone' => GhanaPhone::normalize($row[2]),
                    ],
                );
                $imported++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        fclose($handle);

        AuditLog::record('voters_imported', 'ec', $request->user()->id, [
            'count' => $imported,
        ]);

        return back()->with('status', "Imported {$imported} voter(s).");
    }

    public function markPaperVote(Voter $voter, ManualBallotService $manual): RedirectResponse
    {
        try {
            $manual->lockPaperVote($voter);
        } catch (ElectionSetupException $e) {
            return back()->withErrors(['voters' => $e->getMessage()]);
        }

        return back()->with('status', $voter->name.' is locked after a paper ballot.');
    }

    public function template(): Response
    {
        $csv = "staff_id,name,phone\nAGH00123,Demo Voter,0241231234\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="voters-template.csv"',
        ]);
    }

    public function audit(): View
    {
        return view('ec.audit', [
            'logs' => AuditLog::query()->latest('id')->paginate(100),
        ]);
    }
}
