<?php

namespace App\Services;

use App\Exceptions\ElectionSetupException;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\ManualTally;
use App\Models\PaperBallotSerial;
use App\Models\Voter;
use App\Support\UnopposedVoting;
use Illuminate\Support\Facades\DB;

class ManualBallotService
{
    /**
     * Lock digital voting for a paper ballot and issue an opaque sheet serial.
     *
     * @return array{0: Voter, 1: PaperBallotSerial}
     */
    public function lockPaperVote(Voter $voter, string $actorType = 'ec'): array
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

            $serial = PaperBallotSerial::query()->create([
                'election_id' => $locked->election_id,
                'voter_id' => $locked->id,
                'serial' => PaperBallotSerial::generateUnique(),
                'issued_at' => now(),
            ]);

            AuditLog::record('voter_voted_manual', $actorType, $actorType === 'ec' ? auth()->id() : $locked->id, [
                'staff_id' => $locked->staff_id,
                'paper_serial' => $serial->serial,
            ]);

            return [$locked->fresh(), $serial];
        });
    }

    /**
     * Reprint an already-issued paper ballot (same serial) after a spoiled sheet or printer failure.
     *
     * @return array{0: Voter, 1: PaperBallotSerial}
     */
    public function reprintPaperBallot(Voter $voter): array
    {
        if ($voter->election->isPublished()) {
            throw new ElectionSetupException('Published results cannot change.');
        }

        if (! $voter->votedOnPaper()) {
            throw new ElectionSetupException('This staff member does not have a paper ballot to reprint.');
        }

        $serial = $voter->paperBallotSerial;

        if (! $serial) {
            throw new ElectionSetupException('No paper ballot serial was found for this staff member.');
        }

        AuditLog::record('paper_ballot_reprinted', 'ec', auth()->id(), [
            'staff_id' => $voter->staff_id,
            'paper_serial' => $serial->serial,
        ]);

        return [$voter->fresh(), $serial];
    }

    public function findBySerial(Election $election, string $serial): ?PaperBallotSerial
    {
        $normalized = PaperBallotSerial::normalize($serial);

        if ($normalized === '') {
            return null;
        }

        return PaperBallotSerial::query()
            ->with('voter')
            ->where('election_id', $election->id)
            ->where('serial', $normalized)
            ->first();
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
