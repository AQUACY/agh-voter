<?php

namespace App\Services;

use App\Models\Election;
use App\Models\ManualTally;
use App\Models\Vote;
use App\Support\UnopposedVoting;

class ResultService
{
    public function live(Election $election): array
    {
        $digitalYes = Vote::query()
            ->selectRaw('candidate_id, COUNT(*) as votes')
            ->where('election_id', $election->id)
            ->where(function ($query) {
                $query->whereNull('choice')->orWhere('choice', UnopposedVoting::CHOICE_YES);
            })
            ->groupBy('candidate_id')
            ->pluck('votes', 'candidate_id');

        $digitalNo = Vote::query()
            ->selectRaw('candidate_id, COUNT(*) as votes')
            ->where('election_id', $election->id)
            ->where('choice', UnopposedVoting::CHOICE_NO)
            ->groupBy('candidate_id')
            ->pluck('votes', 'candidate_id');

        $manualRows = ManualTally::query()
            ->where('election_id', $election->id)
            ->get();

        $manualYes = $manualRows
            ->filter(fn ($row) => ($row->choice ?? UnopposedVoting::CHOICE_YES) === UnopposedVoting::CHOICE_YES)
            ->pluck('votes', 'candidate_id');

        $manualNo = $manualRows
            ->filter(fn ($row) => $row->choice === UnopposedVoting::CHOICE_NO)
            ->pluck('votes', 'candidate_id');

        $includeManual = $election->isClosed();

        return [
            'election' => $election->name,
            'status' => $election->status,
            'open' => $election->isOpen(),
            'closed' => $election->isClosed(),
            'published' => $election->isPublished(),
            'include_manual' => $includeManual,
            'generated_at' => now()->toIso8601String(),
            'turnout' => [
                'registered' => $election->voters()->count(),
                'voted' => $election->voters()->whereNotNull('voted_at')->count(),
                'digital' => $election->voters()->where('vote_channel', 'digital')->count(),
                'paper' => $election->voters()->where('vote_channel', 'manual')->count(),
            ],
            'positions' => $election->positions()->with('candidates')->get()->map(function ($position) use (
                $election,
                $digitalYes,
                $digitalNo,
                $manualYes,
                $manualNo,
                $includeManual,
            ) {
                $rules = UnopposedVoting::rulesFor($position, $election);
                $yesNo = $rules !== null;

                $candidates = $position->candidates->map(function ($candidate) use (
                    $digitalYes,
                    $digitalNo,
                    $manualYes,
                    $manualNo,
                    $includeManual,
                    $yesNo,
                ) {
                    $digitalVotes = (int) $digitalYes->get($candidate->id, 0);
                    $manualVotes = $includeManual ? (int) $manualYes->get($candidate->id, 0) : 0;
                    $digitalNoVotes = (int) $digitalNo->get($candidate->id, 0);
                    $manualNoVotes = $includeManual ? (int) $manualNo->get($candidate->id, 0) : 0;

                    return [
                        'id' => $candidate->id,
                        'name' => $candidate->name,
                        'initials' => $candidate->initials(),
                        'photo_url' => $candidate->photoUrl(),
                        'digital_votes' => $digitalVotes,
                        'manual_votes' => $manualVotes,
                        'votes' => $digitalVotes + $manualVotes,
                        'digital_no_votes' => $digitalNoVotes,
                        'manual_no_votes' => $manualNoVotes,
                        'no_votes' => $digitalNoVotes + $manualNoVotes,
                    ];
                })->values();

                $payload = [
                    'position' => $position->name,
                    'yes_no' => $yesNo,
                    'candidates' => $candidates,
                ];

                if ($yesNo) {
                    $yes = (int) $candidates->sum('votes');
                    $no = (int) $candidates->sum('no_votes');
                    $evaluation = UnopposedVoting::evaluate($rules, $yes, $no);

                    $payload['unopposed'] = [
                        'threshold_type' => $rules['threshold_type'],
                        'threshold_value' => $rules['threshold_value'],
                        'fail_outcome' => $rules['fail_outcome'],
                        'fail_note' => $rules['fail_note'],
                        'yes' => $evaluation['yes'],
                        'no' => $evaluation['no'],
                        'total' => $evaluation['total'],
                        'percent' => $evaluation['percent'],
                        'threshold_met' => $evaluation['met'],
                        'outcome' => $evaluation['outcome'],
                        'message' => $evaluation['message'],
                    ];
                    $payload['leading_votes'] = $yes;
                    $payload['tied'] = false;
                    $payload['leaders'] = $evaluation['met']
                        ? $candidates->pluck('name')->values()
                        : collect();
                } else {
                    $max = (int) $candidates->max('votes');
                    $leaders = $candidates->where('votes', $max)->values();

                    $payload['leading_votes'] = $max;
                    $payload['tied'] = $max > 0 && $leaders->count() > 1;
                    $payload['leaders'] = $leaders->pluck('name')->values();
                }

                return $payload;
            })->values(),
        ];
    }
}
