<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchJob extends Model
{
    public const STATUSES = [
        'pending' => 'Bekliyor',
        'running' => 'Çalışıyor',
        'completed' => 'Tamamlandı',
        'partial' => 'Kısmi (kota)',
        'failed' => 'Hata',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'district_ids' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function districts()
    {
        return District::whereIn('id', $this->district_ids ?? [])->orderBy('name')->get();
    }
}
