<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    public const OPEN_STATUSES = ['menunggu', 'diproses'];
    public const CLOSED_STATUSES = ['selesai', 'ditolak', 'dibatalkan'];

    protected $fillable = [
        'report_number', 'user_id', 'reporter_name', 'team_id', 'category_id', 'custom_category',
        'inventory_item_id', 'location_id', 'assigned_to_id', 'title', 'description', 'priority', 'status',
        'follow_up_note', 'first_response_at', 'resolved_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // ---------------------------------------------------------------- relasi

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ReportActivity::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    // ---------------------------------------------------------------- scopes

    /** Batasi laporan sesuai hak akses user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->whereIn('team_id', $user->handledTeamIds() ?: [0]);
        }

        return $query->where('user_id', $user->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** Laporan aktif yang belum ada penanggung jawabnya (antrean regu). */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->open()->whereNull('assigned_to_id');
    }

    // --------------------------------------------------------------- helper

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function categoryDisplayName(): string
    {
        return $this->custom_category ?: ($this->category->name ?? '-');
    }

    public function statusLabel(): string
    {
        return config("halodek.statuses.{$this->status}.label", ucfirst($this->status));
    }

    public function statusTone(): string
    {
        return config("halodek.statuses.{$this->status}.tone", 'gray');
    }

    public function priorityLabel(): string
    {
        return config("halodek.priorities.{$this->priority}.label", ucfirst($this->priority));
    }

    public function priorityTone(): string
    {
        return config("halodek.priorities.{$this->priority}.tone", 'gray');
    }

    /** Lama penyelesaian dalam jam (null bila belum selesai). */
    public function resolutionHours(): ?float
    {
        return $this->resolved_at ? round($this->created_at->diffInMinutes($this->resolved_at) / 60, 1) : null;
    }

    /** Token pendek (16 hex) untuk halaman pantau tanpa login; diturunkan dari APP_KEY. */
    public function publicToken(): string
    {
        return substr(hash_hmac('sha256', 'pantau|'.$this->id, (string) config('app.key')), 0, 16);
    }

    /** Tautan pendek halaman pantau: /p/{id}/{token}. */
    public function publicUrl(): string
    {
        return route('reports.public', ['report' => $this->id, 'token' => $this->publicToken()]);
    }
}
