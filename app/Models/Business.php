<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    // Huni durumları
    public const STATUS_DISCOVERED = 'discovered';

    public const STATUS_DISQUALIFIED = 'disqualified';

    public const STATUS_MANUAL_CONTACT = 'manual_contact';

    public const STATUS_NEEDS_VERIFICATION = 'needs_verification';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_SITE_READY = 'site_ready';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUS_OPTED_OUT = 'opted_out';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_DISCOVERED => 'Bulundu',
        self::STATUS_DISQUALIFIED => 'Elendi',
        self::STATUS_MANUAL_CONTACT => 'Manuel iletişim',
        self::STATUS_NEEDS_VERIFICATION => 'Doğrulama bekliyor',
        self::STATUS_GENERATING => 'Site üretiliyor',
        self::STATUS_SITE_READY => 'Site hazır',
        self::STATUS_APPROVED => 'Onaylandı',
        self::STATUS_SENT => 'Gönderildi',
        self::STATUS_VIEWED => 'Site açıldı',
        self::STATUS_REPLIED => 'Cevap geldi',
        self::STATUS_WON => 'Satış',
        self::STATUS_LOST => 'Kaybedildi',
        self::STATUS_OPTED_OUT => 'Ret (iletişim istemiyor)',
        self::STATUS_FAILED => 'Hata',
    ];

    // Huni sıralaması (rapor için)
    public const FUNNEL = [
        self::STATUS_SITE_READY,
        self::STATUS_APPROVED,
        self::STATUS_SENT,
        self::STATUS_VIEWED,
        self::STATUS_REPLIED,
        self::STATUS_WON,
    ];

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_MANUAL = 'manual';

    public const CHANNELS = [
        self::CHANNEL_WHATSAPP => 'WhatsApp',
        self::CHANNEL_EMAIL => 'E-posta',
        self::CHANNEL_MANUAL => 'Manuel (telefon)',
    ];

    public const CALL_OUTCOMES = [
        'interested' => 'İlgileniyor',
        'not_interested' => 'İlgilenmiyor',
        'call_back' => 'Tekrar ara',
        'no_answer' => 'Ulaşılamadı',
    ];

    public const WEBSITE_NONE = 'none';

    public const WEBSITE_SOCIAL_ONLY = 'social_only';

    public const WEBSITE_REAL = 'real';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reviews' => 'array',
            'types' => 'array',
            'opening_hours' => 'array',
            'rating' => 'float',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function searchJob(): BelongsTo
    {
        return $this->belongsTo(SearchJob::class);
    }

    public function site(): HasOne
    {
        return $this->hasOne(Site::class);
    }

    public function outreachMessages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }

    public function effectiveSetupPrice(): int
    {
        return $this->setup_price
            ?? $this->category?->setup_price
            ?? (int) Setting::get('price_setup', config('leadgen.pricing.setup'));
    }

    public function effectiveMaintenancePrice(): int
    {
        return $this->maintenance_price
            ?? $this->category?->maintenance_price
            ?? (int) Setting::get('price_maintenance', config('leadgen.pricing.maintenance'));
    }

    /**
     * Durumu yalnızca huni içinde ileri taşır (ör. "viewed" iken tekrar "sent" yapmaz).
     */
    public function advanceTo(string $status): void
    {
        $order = array_flip(self::FUNNEL);

        if (! isset($order[$status])) {
            $this->update(['status' => $status]);

            return;
        }

        $current = $order[$this->status] ?? -1;

        if ($order[$status] > $current) {
            $this->update(['status' => $status]);
        }
    }

    public function isOptedOut(): bool
    {
        return OptOut::matches($this->phone, $this->email);
    }
}
