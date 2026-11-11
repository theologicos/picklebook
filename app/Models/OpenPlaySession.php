<?php

namespace App\Models;

use App\Enums\OpenPlaySessionStatus;
use App\Enums\SkillLevel;
use Carbon\CarbonInterface;
use Database\Factories\OpenPlaySessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenPlaySession extends Model
{
    /** @use HasFactory<OpenPlaySessionFactory> */
    use HasFactory;

    protected $fillable = [
        'court_id',
        'title',
        'starts_at',
        'ends_at',
        'max_players',
        'fee_per_player',
        'skill_level',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_players' => 'integer',
            'fee_per_player' => 'integer',
            'skill_level' => SkillLevel::class,
            'status' => OpenPlaySessionStatus::class,
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OpenPlayEntry::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(OpenPlayGame::class);
    }

    // SCOPES e

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', OpenPlaySessionStatus::Cancelled->value);
    }

    // Sessions whose time range intersects [start, end)
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    // Sessions players can still join or are playing in.
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [OpenPlaySessionStatus::Scheduled->value, OpenPlaySessionStatus::Live->value])
            ->where('ends_at', '>', now());
    }

    // HELPERS !!!!

    public function isJoinable(): bool
    {
        return in_array($this->status, [OpenPlaySessionStatus::Scheduled, OpenPlaySessionStatus::Live], true)
            && $this->ends_at->isFuture();
    }

    // Players signed up (and not left). Uses the "joined_count" from withCount() when it was loaded.
    public function joinedCount(): int
    {
        return $this->joined_count ?? $this->entries()->active()->count();
    }

    public function spotsLeft(): int
    {
        return max(0, $this->max_players - $this->joinedCount());
    }

    public function isFull(): bool
    {
        return $this->spotsLeft() === 0;
    }
}