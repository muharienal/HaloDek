<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportActivity extends Model
{
    protected $fillable = ['report_id', 'user_id', 'type', 'message', 'is_internal', 'meta'];

    protected $casts = [
        'is_internal' => 'boolean',
        'meta' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function icon(): string
    {
        return match ($this->type) {
            'created' => 'plus',
            'status' => 'refresh',
            'assigned', 'claimed' => 'user',
            'released' => 'arrow-left',
            'priority' => 'flag',
            'transferred' => 'arrow-right',
            'comment' => 'chat',
            'cancelled' => 'x-circle',
            default => 'dot',
        };
    }
}
