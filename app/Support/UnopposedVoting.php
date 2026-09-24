<?php

namespace App\Support;

use App\Models\Election;
use App\Models\Position;

class UnopposedVoting
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_PER_POSITION = 'per_position';

    public const THRESHOLD_PERCENT = 'percent';

    public const THRESHOLD_COUNT = 'count';

    public const FAIL_OPEN_NOMINATIONS = 'open_nominations';

    public const FAIL_OTHER = 'other';

    public const CHOICE_YES = 'yes';

    public const CHOICE_NO = 'no';

    /**
     * @return array{enabled: bool, threshold_type: string, threshold_value: int, fail_outcome: string, fail_note: ?string}|null
     */
    public static function rulesFor(Position $position, ?Election $election = null): ?array
    {
        $election ??= $position->election;

        if (! $election || ! $election->unopposed_voting_enabled) {
            return null;
        }

        $candidateCount = $position->relationLoaded('candidates')
            ? $position->candidates->count()
            : $position->candidates()->count();

        if ($candidateCount !== 1) {
            return null;
        }

        if ($election->unopposed_voting_scope === self::SCOPE_GLOBAL) {
            return self::normalizeRules(
                $election->unopposed_threshold_type,
                $election->unopposed_threshold_value,
                $election->unopposed_fail_outcome,
                $election->unopposed_fail_note,
            );
        }

        if ($election->unopposed_voting_scope === self::SCOPE_PER_POSITION) {
            if (! $position->unopposed_yes_no) {
                return null;
            }

            return self::normalizeRules(
                $position->unopposed_threshold_type,
                $position->unopposed_threshold_value,
                $position->unopposed_fail_outcome,
                $position->unopposed_fail_note,
            );
        }

        return null;
    }

    public static function usesYesNo(Position $position, ?Election $election = null): bool
    {
        return self::rulesFor($position, $election) !== null;
    }

    /**
     * @param  array{threshold_type: string, threshold_value: int, fail_outcome: string, fail_note: ?string}  $rules
     * @return array{met: bool, yes: int, no: int, total: int, percent: float, outcome: string, message: ?string}
     */
    public static function evaluate(array $rules, int $yes, int $no): array
    {
        $total = $yes + $no;
        $percent = $total > 0 ? round(($yes / $total) * 100, 2) : 0.0;

        $met = match ($rules['threshold_type']) {
            // More than X% of Yes+No (turnout). At 50% that is half + 1 vote.
            self::THRESHOLD_PERCENT => $total > 0 && $yes > ($total * $rules['threshold_value'] / 100),
            self::THRESHOLD_COUNT => $yes >= $rules['threshold_value'],
            default => false,
        };

        if ($met) {
            return [
                'met' => true,
                'yes' => $yes,
                'no' => $no,
                'total' => $total,
                'percent' => $percent,
                'outcome' => 'elected',
                'message' => null,
            ];
        }

        $outcome = $rules['fail_outcome'];
        $message = match ($outcome) {
            self::FAIL_OPEN_NOMINATIONS => 'Yes threshold not met. This office is open for nominations.',
            self::FAIL_OTHER => $rules['fail_note'] ?: 'Yes threshold not met.',
            default => 'Yes threshold not met.',
        };

        return [
            'met' => false,
            'yes' => $yes,
            'no' => $no,
            'total' => $total,
            'percent' => $percent,
            'outcome' => $outcome,
            'message' => $message,
        ];
    }

    /**
     * @return array{enabled: bool, threshold_type: string, threshold_value: int, fail_outcome: string, fail_note: ?string}|null
     */
    private static function normalizeRules(
        ?string $thresholdType,
        mixed $thresholdValue,
        ?string $failOutcome,
        ?string $failNote,
    ): ?array {
        if (! in_array($thresholdType, [self::THRESHOLD_PERCENT, self::THRESHOLD_COUNT], true)) {
            return null;
        }

        if ($thresholdValue === null || $thresholdValue === '') {
            return null;
        }

        if (! in_array($failOutcome, [self::FAIL_OPEN_NOMINATIONS, self::FAIL_OTHER], true)) {
            return null;
        }

        if ($failOutcome === self::FAIL_OTHER && blank($failNote)) {
            return null;
        }

        $value = (int) $thresholdValue;

        if ($thresholdType === self::THRESHOLD_PERCENT && ($value < 1 || $value > 100)) {
            return null;
        }

        if ($thresholdType === self::THRESHOLD_COUNT && $value < 1) {
            return null;
        }

        return [
            'enabled' => true,
            'threshold_type' => $thresholdType,
            'threshold_value' => $value,
            'fail_outcome' => $failOutcome,
            'fail_note' => $failOutcome === self::FAIL_OTHER ? trim((string) $failNote) : null,
        ];
    }
}
