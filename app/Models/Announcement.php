<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'body', 'team_id', 'user_id', 'is_pinned', 'expires_at'];

    protected $casts = [
        'is_pinned' => 'boolean',
        'expires_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Pengumuman yang belum kedaluwarsa. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
        });
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->latest();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->lt(today());
    }
}
