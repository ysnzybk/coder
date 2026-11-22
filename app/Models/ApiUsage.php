<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ücretsiz kota sayaçları (dönem + anahtar bazında). */
class ApiUsage extends Model
{
    protected $table = 'api_usage';

    protected $guarded = ['id'];

    public static function count(string $period, string $key): int
    {
        return (int) static::where('period', $period)->where('key', $key)->value('count');
    }

    public static function hit(string $period, string $key, int $by = 1): void
    {
        $row = static::firstOrCreate(['period' => $period, 'key' => $key], ['count' => 0]);
        $row->increment('count', $by);
    }
}
