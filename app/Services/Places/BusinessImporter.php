<?php

namespace App\Services\Places;

use App\Jobs\GenerateSiteJob;
use App\Models\Business;
use App\Models\SearchJob;
use App\Services\Phone\TurkishPhone;

/**
 * Places sonucunu firmaya dönüştürür ve uygunluk/kanal kararını verir:
 *  - Gerçek web sitesi var / kalıcı kapalı  → elenir
 *  - Ret listesinde                          → opted_out
 *  - Cep telefonu                            → WhatsApp, site otomatik üretilir
 *  - Sabit hat + e-posta                     → E-posta, site otomatik üretilir
 *  - Sabit hat / telefon yok, e-posta yok    → Manuel iletişim, site isteğe bağlı
 */
class BusinessImporter
{
    public function __construct(
        private readonly WebsiteClassifier $classifier,
        private readonly PlacesClient $places,
    ) {}

    /** @return Business|null yeni oluşturulan ve uygun bulunan firma (Google Places yanıtı) */
    public function import(array $place, SearchJob $job, ?int $districtId): ?Business
    {
        return $this->importRow(PlacesNormalizer::fromGooglePlace($place), $job, $districtId);
    }

    /**
     * Normalize edilmiş satırdan firma oluşturur (kaynak: Google ya da dosya).
     *
     * @return Business|null yeni oluşturulan ve uygun bulunan firma
     */
    public function importRow(array $row, SearchJob $job, ?int $districtId): ?Business
    {
        $externalId = $row['external_id'] ?? null;

        if (! $externalId || Business::where('place_id', $externalId)->exists()) {
            return null; // daha önce bulunmuş — tekrar işlenmez
        }

        $website = $row['website'] ?? null;
        $status = $this->classifier->classify($website);

        // Web sitesi alanı boş ama sosyal medya bağlantısı varsa "sadece sosyal"
        if ($status === Business::WEBSITE_NONE && ! empty($row['social_url'])) {
            $status = Business::WEBSITE_SOCIAL_ONLY;
        }

        $business = Business::create([
            'place_id' => $externalId,
            'source' => $row['source'] ?? 'google',
            'source_confidence' => $row['confidence'] ?? null,
            'search_job_id' => $job->id,
            'category_id' => $job->category_id,
            'province_id' => $job->province_id,
            'district_id' => $districtId,
            'name' => $row['name'],
            'address' => $row['address'] ?? null,
            'phone' => TurkishPhone::normalize($row['phone'] ?? null) ?? ($row['phone'] ?? null),
            'phone_type' => TurkishPhone::type($row['phone'] ?? null),
            'email' => $row['email'] ?? null,
            'website_raw' => $website,
            'social_url' => $row['social_url'] ?? null,
            'website_status' => $status,
            'rating' => $row['rating'] ?? null,
            'review_count' => $row['review_count'] ?? 0,
            'types' => $row['types'] ?? [],
            'lat' => $row['lat'] ?? null,
            'lng' => $row['lng'] ?? null,
            'maps_url' => $row['maps_url'] ?? null,
            'business_status' => $row['business_status'] ?? null,
        ]);

        return $this->qualify($business) ? $business : null;
    }

    /** Uygunluk ve kanal kararını verir; uygun ise true. */
    public function qualify(Business $business): bool
    {
        if ($business->website_status === Business::WEBSITE_REAL) {
            $business->update(['status' => Business::STATUS_DISQUALIFIED, 'disqualify_reason' => 'Web sitesi var']);

            return false;
        }

        if (in_array($business->business_status, ['CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY'], true)) {
            $business->update(['status' => Business::STATUS_DISQUALIFIED, 'disqualify_reason' => 'İşletme kapalı']);

            return false;
        }

        if ($business->isOptedOut()) {
            $business->update(['status' => Business::STATUS_OPTED_OUT]);

            return false;
        }

        $channel = match (true) {
            $business->phone_type === TurkishPhone::TYPE_MOBILE => Business::CHANNEL_WHATSAPP,
            filled($business->email) => Business::CHANNEL_EMAIL,
            default => Business::CHANNEL_MANUAL,
        };

        $business->update([
            'contact_channel' => $channel,
            // Dosya kaynaklı (açık veri) kayıtlarda "web sitesi yok" bilgisi güvenilir olmayabilir:
            // önce elle doğrulanır; doğrulanana kadar site üretimi/AI bütçesi harcanmaz.
            'status' => $business->source === 'file'
                ? Business::STATUS_NEEDS_VERIFICATION
                : ($channel === Business::CHANNEL_MANUAL ? Business::STATUS_MANUAL_CONTACT : Business::STATUS_DISCOVERED),
        ]);

        return true;
    }

    /** Doğrulama sonrası: "Gerçekten sitesi yok" → normal akışa al (kanal/durum + site üretimi). */
    public function confirmNoWebsite(Business $business): void
    {
        if ($business->status !== Business::STATUS_NEEDS_VERIFICATION) {
            return;
        }

        $business->update(['status' => $business->contact_channel === Business::CHANNEL_MANUAL
            ? Business::STATUS_MANUAL_CONTACT
            : Business::STATUS_DISCOVERED]);

        $this->dispatchGenerationIfAutomatic($business->refresh());
    }

    /** Doğrulama sonrası: "Sitesi var" → elenir. */
    public function rejectHasWebsite(Business $business): void
    {
        $business->update([
            'status' => Business::STATUS_DISQUALIFIED,
            'website_status' => Business::WEBSITE_REAL,
            'disqualify_reason' => 'Web sitesi var (elle doğrulandı)',
        ]);
    }

    /** Yorum ve çalışma saatlerini (yalnızca uygun firmalar için) çeker. */
    public function enrich(Business $business): void
    {
        $details = $this->places->details($business->place_id);

        $reviews = collect($details['reviews'] ?? [])
            ->map(fn (array $r) => [
                'author' => $r['authorAttribution']['displayName'] ?? 'Google kullanıcısı',
                'rating' => $r['rating'] ?? null,
                'text' => $r['text']['text'] ?? $r['originalText']['text'] ?? '',
                'time' => $r['relativePublishTimeDescription'] ?? null,
            ])
            ->filter(fn (array $r) => $r['text'] !== '')
            ->values()
            ->all();

        $business->update([
            'reviews' => $reviews,
            'opening_hours' => $details['regularOpeningHours']['weekdayDescriptions'] ?? null,
            'notes' => $business->notes ?? ($details['editorialSummary']['text'] ?? null),
        ]);
    }

    /** Uygun ve otomatik akıştaki (WhatsApp/E-posta) firmalar için site üretimini başlatır. */
    public function dispatchGenerationIfAutomatic(Business $business): void
    {
        if (in_array($business->contact_channel, [Business::CHANNEL_WHATSAPP, Business::CHANNEL_EMAIL], true)
            && $business->status === Business::STATUS_DISCOVERED) {
            GenerateSiteJob::dispatch($business->id);
        }
    }
}
