<?php

namespace App\Models;

use App\Exceptions\ElectionSetupException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Election extends Model
{
    protected $fillable = [
        'name',
        'status',
        'opens_at',
        'closes_at',
        'published_at',
        'unopposed_voting_enabled',
        'unopposed_voting_scope',
        'unopposed_threshold_type',
        'unopposed_threshold_value',
        'unopposed_fail_outcome',
        'unopposed_fail_note',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'published_at' => 'datetime',
            'unopposed_voting_enabled' => 'boolean',
            'unopposed_threshold_value' => 'integer',
        ];
    }

    public function manualTallies(): HasMany
    {
        return $this->hasMany(ManualTally::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('sort_order');
    }

    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function candidates(): HasManyThrough
    {
        return $this->hasManyThrough(Candidate::class, Position::class);
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public static function currentOrFail(): self
    {
        return static::current() ?? throw new ElectionSetupException('No election is configured.');
    }

    public function isOpen(): bool
    {
        $now = now();

        return $this->status === 'open'
            && $now->gte($this->opens_at)
            && $now->lte($this->closes_at);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed' || ($this->status === 'open' && now()->gt($this->closes_at));
    }

    public function hasVotes(): bool
    {
        return $this->votes()->exists()
            || $this->voters()->whereNotNull('voted_at')->exists();
    }

    public function ballotLocked(): bool
    {
        return $this->isClosed() || $this->hasVotes();
    }

    public function registerLocked(): bool
    {
        return $this->isClosed();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function tallyLocked(): bool
    {
        return $this->isPublished();
    }
}
