<?php

namespace App\Models;

use App\Enums\OpenPlayEntryStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OpenPlayEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OpenPlayEntry extends Model
{
    /** @use HasFactory<OpenPlayEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'open_play_session_id',
        'user_id',
        'status',
        'payment_method',
        'payment_status',
        'gcash_reference',
        'amount',
        'queued_at',
        'games_played',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpenPlayEntryStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'amount' => 'integer',
            'games_played' => 'integer',
            'queued_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpenPlaySession::class, 'open_play_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(OpenPlayGame::class, 'open_play_game_players', 'open_play_entry_id', 'open_play_game_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', OpenPlayEntryStatus::Left->value);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', OpenPlayEntryStatus::Waiting->value);
    }

    // First in line first, earliest joined, then fewest games played, then sign-up order.
    public function scopeQueueOrder(Builder $query): Builder
    {
        return $query->orderBy('queued_at')->orderBy('games_played')->orderBy('id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }
}