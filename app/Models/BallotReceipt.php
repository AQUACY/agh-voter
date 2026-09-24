<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BallotReceipt extends Model
{
    protected $fillable = [
        'election_id',
        'voter_id',
        'receipt_code',
        'cast_at',
    ];

    protected function casts(): array
    {
        return [
            'cast_at' => 'datetime',
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

    public function formatted(): string
    {
        return PaperBallotSerial::format($this->receipt_code);
    }

    public static function generateUnique(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (self::query()->where('receipt_code', $code)->exists());

        return $code;
    }
}
