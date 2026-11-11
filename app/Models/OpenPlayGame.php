<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OpenPlayGame extends Model
{
    protected $fillable = ['open_play_session_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpenPlaySession::class, 'open_play_session_id');
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(OpenPlayEntry::class, 'open_play_game_players', 'open_play_game_id', 'open_play_entry_id');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isFinished(): bool
    {
        return $this->ended_at !== null;
    }
}