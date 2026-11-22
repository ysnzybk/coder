<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    public const VARIABLES = [
        '{firma_adi}' => 'Firma adı',
        '{site_linki}' => 'Takipli site linki',
        '{kurulum_ucreti}' => 'Kurulum ücreti (ör. 15.000)',
        '{bakim_ucreti}' => 'Aylık bakım ücreti (ör. 1.000)',
        '{ilce}' => 'İlçe',
        '{il}' => 'İl',
        '{ret_linki}' => 'İletişim istemiyorum linki',
        '{gonderen_adi}' => 'Gönderen adı (Ayarlar)',
        '{gonderen_telefon}' => 'Gönderen telefonu (Ayarlar)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function defaultFor(string $channel): ?self
    {
        return static::where('channel', $channel)->orderByDesc('is_default')->orderBy('id')->first();
    }
}
