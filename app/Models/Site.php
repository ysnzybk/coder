<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Site extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'images' => 'array',
            'is_active' => 'boolean',
            'is_sold' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function url(?string $token = null): string
    {
        $url = rtrim(config('app.url'), '/').'/'.$this->slug;

        return $token ? $url.'?r='.$token : $url;
    }

    /** @return array<string, string> anahtar => public URL */
    public function imageUrls(): array
    {
        return collect($this->images ?? [])
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();
    }
}
