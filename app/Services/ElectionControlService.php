<?php

namespace App\Services;

use App\Exceptions\ElectionSetupException;
use App\Models\AuditLog;
use App\Models\Election;
use App\Support\UnopposedVoting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ElectionControlService
{
    public function update(Election $election, array $data): Election
    {
        if ($election->isClosed()) {
            throw new ElectionSetupException('A closed election cannot be edited.');
        }

        $opens = Carbon::parse($data['opens_at'], config('app.timezone'));
        $closes = Carbon::parse($data['closes_at'], config('app.timezone'));

        if ($closes->lte($opens)) {
            throw new ElectionSetupException('Closing time must be after opening time.');
        }

        $unopposed = $election->ballotLocked()
            ? [
                'unopposed_voting_enabled' => $election->unopposed_voting_enabled,
                'unopposed_voting_scope' => $election->unopposed_voting_scope,
                'unopposed_threshold_type' => $election->unopposed_threshold_type,
                'unopposed_threshold_value' => $election->unopposed_threshold_value,
                'unopposed_fail_outcome' => $election->unopposed_fail_outcome,
                'unopposed_fail_note' => $election->unopposed_fail_note,
            ]
            : $this->normalizeUnopposedSettings($data);

        $election->update([
            'name' => $data['name'],
            'opens_at' => $opens,
            'closes_at' => $closes,
            ...$unopposed,
        ]);

        AuditLog::record('election_updated', 'ec', auth()->id(), [
            'name' => $election->name,
        ]);

        return $election->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     unopposed_voting_enabled: bool,
     *     unopposed_voting_scope: ?string,
     *     unopposed_threshold_type: ?string,
     *     unopposed_threshold_value: ?int,
     *     unopposed_fail_outcome: ?string,
     *     unopposed_fail_note: ?string
     * }
     */
    public function normalizeUnopposedSettings(array $data): array
    {
        $enabled = (bool) ($data['unopposed_voting_enabled'] ?? false);

        if (! $enabled) {
            return [
                'unopposed_voting_enabled' => false,
                'unopposed_voting_scope' => null,
                'unopposed_threshold_type' => null,
                'unopposed_threshold_value' => null,
                'unopposed_fail_outcome' => null,
                'unopposed_fail_note' => null,
            ];
        }

        $scope = $data['unopposed_voting_scope'] ?? null;

        if (! in_array($scope, [UnopposedVoting::SCOPE_GLOBAL, UnopposedVoting::SCOPE_PER_POSITION], true)) {
            throw new ElectionSetupException('Choose Global or Per position for unopposed Yes/No voting.');
        }

        if ($scope === UnopposedVoting::SCOPE_PER_POSITION) {
            return [
                'unopposed_voting_enabled' => true,
                'unopposed_voting_scope' => $scope,
                'unopposed_threshold_type' => null,
                'unopposed_threshold_value' => null,
                'unopposed_fail_outcome' => null,
                'unopposed_fail_note' => null,
            ];
        }

        $type = $data['unopposed_threshold_type'] ?? null;
        $value = $data['unopposed_threshold_value'] ?? null;
        $outcome = $data['unopposed_fail_outcome'] ?? null;
        $note = isset($data['unopposed_fail_note']) ? trim((string) $data['unopposed_fail_note']) : null;

        $this->assertThresholdBundle($type, $value, $outcome, $note, 'global unopposed');

        return [
            'unopposed_voting_enabled' => true,
            'unopposed_voting_scope' => $scope,
            'unopposed_threshold_type' => $type,
            'unopposed_threshold_value' => (int) $value,
            'unopposed_fail_outcome' => $outcome,
            'unopposed_fail_note' => $outcome === UnopposedVoting::FAIL_OTHER ? $note : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     unopposed_yes_no: bool,
     *     unopposed_threshold_type: ?string,
     *     unopposed_threshold_value: ?int,
     *     unopposed_fail_outcome: ?string,
     *     unopposed_fail_note: ?string
     * }
     */
    public function normalizePositionUnopposedSettings(array $data): array
    {
        $enabled = (bool) ($data['unopposed_yes_no'] ?? false);

        if (! $enabled) {
            return [
                'unopposed_yes_no' => false,
                'unopposed_threshold_type' => null,
                'unopposed_threshold_value' => null,
                'unopposed_fail_outcome' => null,
                'unopposed_fail_note' => null,
            ];
        }

        $type = $data['unopposed_threshold_type'] ?? null;
        $value = $data['unopposed_threshold_value'] ?? null;
        $outcome = $data['unopposed_fail_outcome'] ?? null;
        $note = isset($data['unopposed_fail_note']) ? trim((string) $data['unopposed_fail_note']) : null;

        $this->assertThresholdBundle($type, $value, $outcome, $note, 'this position');

        return [
            'unopposed_yes_no' => true,
            'unopposed_threshold_type' => $type,
            'unopposed_threshold_value' => (int) $value,
            'unopposed_fail_outcome' => $outcome,
            'unopposed_fail_note' => $outcome === UnopposedVoting::FAIL_OTHER ? $note : null,
        ];
    }

    private function assertThresholdBundle(
        mixed $type,
        mixed $value,
        mixed $outcome,
        ?string $note,
        string $label,
    ): void {
        if (! in_array($type, [UnopposedVoting::THRESHOLD_PERCENT, UnopposedVoting::THRESHOLD_COUNT], true)) {
            throw new ElectionSetupException("Choose a Yes threshold type for {$label} voting.");
        }

        if ($value === null || $value === '' || (int) $value < 1) {
            throw new ElectionSetupException("Enter a Yes threshold value for {$label} voting.");
        }

        if ($type === UnopposedVoting::THRESHOLD_PERCENT && (int) $value > 100) {
            throw new ElectionSetupException('A percent threshold must be between 1 and 100.');
        }

        if (! in_array($outcome, [UnopposedVoting::FAIL_OPEN_NOMINATIONS, UnopposedVoting::FAIL_OTHER], true)) {
            throw new ElectionSetupException("Choose what happens if the Yes threshold is not met for {$label} voting.");
        }

        if ($outcome === UnopposedVoting::FAIL_OTHER && blank($note)) {
            throw new ElectionSetupException('Add a note for the Other outcome when the Yes threshold is not met.');
        }
    }

    public function open(Election $election): Election
    {
        if ($election->isClosed() && $election->hasVotes()) {
            throw new ElectionSetupException('A closed election with votes cannot be reopened.');
        }

        $this->assertReadyToOpen($election);

        return DB::transaction(function () use ($election) {
            $election->update([
                'status' => 'open',
                'opens_at' => $election->opens_at->isFuture() ? now() : $election->opens_at,
            ]);

            AuditLog::record('election_opened', 'ec', auth()->id());

            return $election->fresh();
        });
    }

    public function close(Election $election): Election
    {
        if ($election->status === 'closed') {
            throw new ElectionSetupException('The election is already closed.');
        }

        return DB::transaction(function () use ($election) {
            $election->update([
                'status' => 'closed',
                'closes_at' => now()->lt($election->closes_at) ? now() : $election->closes_at,
            ]);

            AuditLog::record('election_closed', 'ec', auth()->id());

            return $election->fresh();
        });
    }

    public function restoreVotingPeriod(Election $election): Election
    {
        if ($election->status === 'open' && ! $election->isPublished() && $election->closes_at->isFuture()) {
            throw new ElectionSetupException('Voting is already open.');
        }

        $closesAt = $election->closes_at->isFuture()
            ? $election->closes_at
            : now()->addDay();

        return DB::transaction(function () use ($election, $closesAt) {
            $wasPublished = $election->isPublished();

            $election->update([
                'status' => 'open',
                'published_at' => null,
                'opens_at' => $election->opens_at->isFuture() ? now() : $election->opens_at,
                'closes_at' => $closesAt,
            ]);

            AuditLog::record('election_voting_restored', 'admin', auth()->id(), [
                'unpublished' => $wasPublished,
                'closes_at' => $closesAt->toIso8601String(),
            ]);

            return $election->fresh();
        });
    }

    public function returnToSetup(Election $election): Election
    {
        if ($election->hasVotes()) {
            throw new ElectionSetupException('The ballot cannot change after a vote has been cast.');
        }

        if ($election->status === 'closed' && $election->hasVotes()) {
            throw new ElectionSetupException('A closed election with votes cannot return to setup.');
        }

        $election->update(['status' => 'draft']);

        AuditLog::record('election_returned_to_setup', 'ec', auth()->id());

        return $election->fresh();
    }

    public function assertBallotEditable(Election $election): void
    {
        if ($election->ballotLocked()) {
            throw new ElectionSetupException('The ballot is locked after voting starts, or after the election closes.');
        }
    }

    public function assertRegisterEditable(Election $election): void
    {
        if ($election->registerLocked()) {
            throw new ElectionSetupException('The voter register is locked after the election closes.');
        }
    }

    private function assertReadyToOpen(Election $election): void
    {
        $positions = $election->positions()->with('candidates')->get();

        if ($positions->isEmpty()) {
            throw new ElectionSetupException('Add at least one position before opening the election.');
        }

        foreach ($positions as $position) {
            if ($position->candidates->isEmpty()) {
                throw new ElectionSetupException("{$position->name} needs at least one candidate.");
            }
        }

        if ($election->unopposed_voting_enabled) {
            if (! in_array($election->unopposed_voting_scope, [UnopposedVoting::SCOPE_GLOBAL, UnopposedVoting::SCOPE_PER_POSITION], true)) {
                throw new ElectionSetupException('Choose Global or Per position for unopposed Yes/No voting before opening.');
            }

            $singleCandidatePositions = $positions->filter(fn ($position) => $position->candidates->count() === 1);

            if ($election->unopposed_voting_scope === UnopposedVoting::SCOPE_GLOBAL && $singleCandidatePositions->isNotEmpty()) {
                $sample = $singleCandidatePositions->first();
                if (UnopposedVoting::rulesFor($sample, $election) === null) {
                    throw new ElectionSetupException('Complete the global unopposed Yes/No settings before opening.');
                }
            }

            if ($election->unopposed_voting_scope === UnopposedVoting::SCOPE_PER_POSITION) {
                foreach ($singleCandidatePositions as $position) {
                    if (! $position->unopposed_yes_no) {
                        continue;
                    }

                    if (UnopposedVoting::rulesFor($position, $election) === null) {
                        throw new ElectionSetupException("Complete Yes/No settings for {$position->name} before opening.");
                    }
                }
            }
        }

        if ($election->voters()->count() < 1) {
            throw new ElectionSetupException('Import or add at least one voter before opening.');
        }

        if ($election->closes_at->lte(now())) {
            throw new ElectionSetupException('Set a closing time in the future before opening.');
        }
    }
}
