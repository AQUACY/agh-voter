<?php

namespace App\Services;

use App\Exceptions\ElectionSetupException;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ManualTally;
use App\Models\Voter;
use App\Support\UnopposedVoting;
use Illuminate\Support\Facades\DB;

class ManualBallotService
{
    public function lockPaperVote(Voter $voter, string $actorType = 'ec'): Voter
    {
        if ($voter->election->isPublished()) {
            throw new ElectionSetupException('Published results cannot change.');
        }

        return DB::transaction(function () use ($voter, $actorType) {
            /** @var Voter $locked */
            $locked = Voter::query()->whereKey($voter->id)->lockForUpdate()->firstOrFail();

            if ($locked->hasVoted()) {
                throw new ElectionSetupException('This staff member has already voted.');
            }

            $locked->update([
                'voted_at' => now(),
                'vote_channel' => 'manual',
            ]);

            AuditLog::record('voter_voted_manual', $actorType, $actorType === 'ec' ? auth()->id() : $locked->id, [
                'staff_id' => $locked->staff_id,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<int|string, mixed>  $counts
     * @param  array<int|string, mixed>  $noCounts
     */
    public function saveTallies(Election $election, array $counts, array $noCounts = []): void
    {
        if (! $election->isClosed()) {
            throw new ElectionSetupException('Paper counts can be entered only after the election closes.');
        }

        if ($election->isPublished()) {
            throw new ElectionSetupException('Published results cannot change.');
        }

        $election->load('positions.candidates');
        $validIds = $election->candidates()->pluck('candidates.id');
        $yesNoCandidateIds = $election->positions
            ->filter(fn ($position) => UnopposedVoting::usesYesNo($position, $election))
            ->flatMap(fn ($position) => $position->candidates->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($election, $counts, $noCounts, $validIds, $yesNoCandidateIds) {
            foreach ($counts as $candidateId => $votes) {
                $id = (int) $candidateId;

                if (! $validIds->contains($id)) {
                    throw new ElectionSetupException('A candidate on the tally sheet is not valid.');
                }

                ManualTally::query()->updateOrCreate(
                    [
                        'election_id' => $election->id,
                        'candidate_id' => $id,
                        'choice' => UnopposedVoting::CHOICE_YES,
                    ],
                    ['votes' => max(0, (int) $votes)],
                );
            }

            foreach ($noCounts as $candidateId => $votes) {
                $id = (int) $candidateId;

                if (! in_array($id, $yesNoCandidateIds, true)) {
                    throw new ElectionSetupException('No counts are only allowed for unopposed Yes/No offices.');
                }

                ManualTally::query()->updateOrCreate(
                    [
                        'election_id' => $election->id,
                        'candidate_id' => $id,
                        'choice' => UnopposedVoting::CHOICE_NO,
                    ],
                    ['votes' => max(0, (int) $votes)],
                );
            }

            AuditLog::record('manual_tallies_saved', 'ec', auth()->id());
        });
    }

    public function publish(Election $election): Election
    {
        if (! $election->isClosed()) {
            throw new ElectionSetupException('Close the election before publishing official results.');
        }

        if ($election->isPublished()) {
            throw new ElectionSetupException('Results are already published.');
        }

        $election->update(['published_at' => now()]);

        AuditLog::record('results_published', 'ec', auth()->id());

        return $election->fresh();
    }
}
