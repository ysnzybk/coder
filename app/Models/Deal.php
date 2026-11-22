<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    public const STATUSES = [
        'active' => 'Aktif',
        'cancelled' => 'İptal',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'cancelled_at' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
