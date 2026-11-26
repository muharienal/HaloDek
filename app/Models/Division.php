<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Divisi, mis. "SDM, Umum & K3". Berisi beberapa Bidang. */
class Division extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class)->orderBy('sort_order')->orderBy('name');
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
