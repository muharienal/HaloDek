<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Item inventaris: barang, ruangan, lisensi, atau apa pun yang dicatat sebuah regu. */
class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', 'inventory_category_id', 'code', 'name', 'status',
        'holder_id', 'location_id', 'acquired_at', 'due_date', 'note',
    ];

    protected $casts = [
        'acquired_at' => 'date',
        'due_date' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function scopeForTeams(Builder $query, array $teamIds): Builder
    {
        return $query->whereIn('team_id', $teamIds);
    }

    /** Jatuh tempo sudah lewat atau dalam 30 hari ke depan. */
    public function scopeDueSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('due_date')->where('due_date', '<=', now()->addDays($days)->toDateString());
    }

    public function statusLabel(): string
    {
        return config("halodek.item_statuses.{$this->status}.label", ucfirst($this->status));
    }

    public function statusTone(): string
    {
        return config("halodek.item_statuses.{$this->status}.tone", 'gray');
    }

    /** 'lewat' | 'segera' | null — berdasarkan jatuh tempo. */
    public function dueState(): ?string
    {
        if (! $this->due_date) {
            return null;
        }

        $days = Carbon::today()->diffInDays($this->due_date, false);

        return $days < 0 ? 'lewat' : ($days <= 30 ? 'segera' : null);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
