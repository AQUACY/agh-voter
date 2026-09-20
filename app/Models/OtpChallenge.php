<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    protected $fillable = [
        'voter_id',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'superseded_at',
        'requested_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function isActive(): bool
    {
        return $this->consumed_at === null
            && $this->superseded_at === null
            && $this->expires_at->isFuture();
    }
}
