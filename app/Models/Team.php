<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    use HasFactory;

    public const SLUG_IT = 'it';
    public const SLUG_SARPRAS = 'sarpras';
    public const SLUG_KESEKRETARIATAN = 'kesekretariatan';
    public const SLUG_KEAMANAN = 'keamanan';

    protected $fillable = [
        'name', 'slug', 'code', 'description', 'icon', 'color',
        'is_active', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /** Seluruh anggota regu (Kepala Regu + Staf). */
    public function users(): HasMany
    {
        return $this->hasMany(User::class)->whereIn('role', User::TEAM_ROLES);
    }

    public function head(): HasOne
    {
        return $this->hasOne(User::class)->where('role', User::ROLE_KEPALA_REGU)->where('is_active', true);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_STAF);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order')->orderBy('name');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Warna hex dari palet konfigurasi. */
    public function hex(): string
    {
        return config("halodek.team_colors.{$this->color}", '#1F6FEB');
    }
}
