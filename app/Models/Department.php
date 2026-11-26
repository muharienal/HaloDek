<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bidang, mis. "Umum & IT", di bawah satu Divisi. Regu (IT, Keamanan, …) berada di bawah Bidang. */
class Department extends Model
{
    protected $fillable = ['division_id', 'name', 'sort_order'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
