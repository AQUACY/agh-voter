<?php

namespace App\Services;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\ElectionUnavailableException;
use App\Exceptions\InvalidBallotException;
use App\Models\AuditLog;
use App\Models\BallotReceipt;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Models\Voter;
use App\Support\UnopposedVoting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BallotService
{
    public function payload(Election $election): array
    {
        return [
            'election' => $election->name,
            'positions' => $election->positions()->with('candidates')->get()->map(function ($position) use ($election) {
                $yesNo = UnopposedVoting::usesYesNo($position, $election);
                $unopposed = $position->candidates->count() === 1;

                return [
                    'id' => $position->id,
                    'position' => $position->name,
                    'unopposed' => $unopposed,
                    'yes_no' => $yesNo,
                    'candidates' => $position->candidates->map(fn ($candidate) => [
                        'id' => $candidate->id,
                        'name' => $candidate->name,
                        'initials' => $candidate->initials(),
                        'photo_url' => $candidate->photoUrl(),
                    ])->values(),
                ];
            })->values(),
        ];
    }

    public function cast(Voter $voter, array $selections): BallotReceipt
    {
        return DB::transaction(function () use ($voter, $selections) {
            /** @var Voter $locked */
            $locked = Voter::query()->whereKey($voter->id)->lockForUpdate()->firstOrFail();
            $election = Election::query()->whereKey($locked->election_id)->lockForUpdate()->firstOrFail();

            if (! $election->isOpen()) {
                throw new ElectionUnavailableException('Voting is not open.');
            }

            if ($locked->hasVoted()) {
                throw new AlreadyVotedException('This Staff ID has already voted.');
            }

            $positions = $election->positions()->with('candidates')->get();

            if ($positions->isEmpty()) {
                throw new InvalidBallotException('This election has no positions.');
            }

            $normalized = collect($selections)->mapWithKeys(function ($row) {
                $positionId = (int) ($row['position_id'] ?? 0);
                $candidateId = isset($row['candidate_id']) && $row['candidate_id'] !== ''
                    ? (int) $row['candidate_id']
                    : null;
                $choice = isset($row['choice']) ? strtolower(trim((string) $row['choice'])) : null;

                return [$positionId => [
                    'candidate_id' => $candidateId,
                    'choice' => $choice,
                ]];
            });

            $resolved = [];

            foreach ($positions as $position) {
                $row = $normalized->get($position->id);
                $yesNo = UnopposedVoting::usesYesNo($position, $election);

                if (! is_array($row)) {
                    throw new InvalidBallotException('Select a candidate for every position.');
                }

                if ($yesNo) {
                    $choice = $row['choice'] ?? null;
                    $candidateId = $row['candidate_id'];
                    $onlyCandidate = $position->candidates->first();

                    if (! in_array($choice, [UnopposedVoting::CHOICE_YES, UnopposedVoting::CHOICE_NO], true)) {
                        throw new InvalidBallotException("Choose Yes or No for {$position->name}.");
                    }

                    if (! $onlyCandidate || $candidateId !== (int) $onlyCandidate->id) {
                        throw new InvalidBallotException('One of the selected candidates is not valid.');
                    }

                    $resolved[$position->id] = [
                        'candidate_id' => $candidateId,
                        'choice' => $choice,
                    ];

                    continue;
                }

                $candidateId = $row['candidate_id'];

                if (! $candidateId) {
                    throw new InvalidBallotException('Select a candidate for every position.');
                }

                $allowed = $position->candidates->pluck('id');

                if (! $allowed->contains($candidateId)) {
                    throw new InvalidBallotException('One of the selected candidates is not valid.');
                }

                if (! Candidate::query()->whereKey($candidateId)->where('position_id', $position->id)->exists()) {
                    throw new InvalidBallotException('One of the selected candidates is not valid.');
                }

                $resolved[$position->id] = [
                    'candidate_id' => $candidateId,
                    'choice' => null,
                ];
            }

            if (collect($resolved)->keys()->diff($positions->pluck('id'))->isNotEmpty()) {
                throw new InvalidBallotException('The ballot contains an unknown position.');
            }

            $now = now();

            foreach ($positions as $position) {
                Vote::query()->create([
                    'election_id' => $election->id,
                    'position_id' => $position->id,
                    'candidate_id' => $resolved[$position->id]['candidate_id'],
                    'choice' => $resolved[$position->id]['choice'],
                    'created_at' => $now,
                ]);
            }

            $receipt = BallotReceipt::query()->create([
                'election_id' => $election->id,
                'voter_id' => $locked->id,
                'receipt_code' => strtoupper(Str::random(10)),
                'cast_at' => $now,
            ]);

            $locked->update([
                'voted_at' => $now,
                'vote_channel' => 'digital',
            ]);

            AuditLog::record('ballot_cast', 'voter', $locked->id, [
                'staff_id' => $locked->staff_id,
                'receipt' => $receipt->receipt_code,
            ]);

            return $receipt;
        });
    }
}
