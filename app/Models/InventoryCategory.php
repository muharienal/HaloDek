<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kategori inventaris milik satu regu (mis. Laptop, AC, Ruangan, Software). */
class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'description', 'sort_order'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
