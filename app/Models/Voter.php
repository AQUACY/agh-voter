<?php

namespace App\Models;

use App\Support\GhanaPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voter extends Model
{
    protected $fillable = [
        'election_id',
        'staff_id',
        'name',
        'phone',
        'voted_at',
        'vote_channel',
    ];

    protected function casts(): array
    {
        return [
            'voted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Voter $voter): void {
            $voter->staff_id = strtoupper(trim($voter->staff_id));
            $voter->phone = GhanaPhone::normalize($voter->phone);
        });
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class);
    }

    public function hasVoted(): bool
    {
        return $this->voted_at !== null;
    }

    public function votedOnPaper(): bool
    {
        return $this->hasVoted() && $this->vote_channel === 'manual';
    }

    public function voteChannelLabel(): string
    {
        if (! $this->hasVoted()) {
            return 'Not voted';
        }

        return $this->vote_channel === 'manual' ? 'Paper' : 'Digital';
    }

    public function maskedPhone(): string
    {
        return GhanaPhone::mask($this->phone);
    }
}
