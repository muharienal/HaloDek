<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'building', 'floor', 'pic_name', 'note'];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function fullName(): string
    {
        $extra = array_filter([$this->building, $this->floor ? 'Lt. '.$this->floor : null]);

        return $extra ? $this->name.' ('.implode(', ', $extra).')' : $this->name;
    }
}
