<?php

namespace App\Models;

use App\Services\Phone\TurkishPhone;
use Illuminate\Database\Eloquent\Model;

class OptOut extends Model
{
    protected $guarded = ['id'];

    public static function matches(?string $phone, ?string $email): bool
    {
        $values = array_filter([
            $phone ? TurkishPhone::normalize($phone) : null,
            $email ? mb_strtolower(trim($email)) : null,
        ]);

        return $values !== [] && static::whereIn('value', $values)->exists();
    }

    public static function register(?string $phone, ?string $email, string $source): void
    {
        if ($phone && ($normalized = TurkishPhone::normalize($phone))) {
            static::firstOrCreate(['value' => $normalized], ['type' => 'phone', 'source' => $source]);
        }

        if ($email) {
            static::firstOrCreate(['value' => mb_strtolower(trim($email))], ['type' => 'email', 'source' => $source]);
        }
    }
}
