<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'election_id',
        'name',
        'sort_order',
        'max_selections',
        'unopposed_yes_no',
        'unopposed_threshold_type',
        'unopposed_threshold_value',
        'unopposed_fail_outcome',
        'unopposed_fail_note',
    ];

    protected function casts(): array
    {
        return [
            'unopposed_yes_no' => 'boolean',
            'unopposed_threshold_value' => 'integer',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('sort_order');
    }
}
