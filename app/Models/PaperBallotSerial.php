<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaperBallotSerial extends Model
{
    protected $fillable = [
        'election_id',
        'voter_id',
        'serial',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public static function normalize(string $serial): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $serial) ?? '');
    }

    public function formatted(): string
    {
        return self::format($this->serial);
    }

    public static function format(string $serial): string
    {
        $raw = self::normalize($serial);

        if (strlen($raw) === 12) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4);
        }

        return $raw;
    }

    public static function generateUnique(): string
    {
        do {
            $serial = strtoupper(Str::random(12));
        } while (self::query()->where('serial', $serial)->exists());

        return $serial;
    }
}
