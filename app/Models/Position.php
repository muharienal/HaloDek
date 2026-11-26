<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jabatan, mis. "Kepala Regu IT", "Staf Keuangan". */
class Position extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
