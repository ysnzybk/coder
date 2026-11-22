<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const TEMPLATES = [
        'petshop' => 'Pet shop / Veteriner',
        'restoran' => 'Restoran / Kafe / Pastane',
        'guzellik' => 'Kuaför / Berber / Güzellik',
        'oto' => 'Oto servis / Lastik / Yıkama',
        'genel' => 'Genel işletme',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
