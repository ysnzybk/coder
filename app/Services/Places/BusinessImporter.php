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

    /** @return Business|null yeni oluşturulan ve uygun bulunan firma */
    public function import(array $place, SearchJob $job, ?int $districtId): ?Business
    {
        $placeId = $place['id'] ?? null;

        if (! $placeId || Business::where('place_id', $placeId)->exists()) {
            return null; // daha önce bulunmuş — tekrar işlenmez
        }

        $phoneRaw = $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null;
        $website = $place['websiteUri'] ?? null;

        $business = Business::create([
            'place_id' => $placeId,
            'search_job_id' => $job->id,
            'category_id' => $job->category_id,
            'province_id' => $job->province_id,
            'district_id' => $districtId,
            'name' => $place['displayName']['text'] ?? 'İsimsiz işletme',
            'address' => $place['formattedAddress'] ?? null,
            'phone' => TurkishPhone::normalize($phoneRaw) ?? $phoneRaw,
            'phone_type' => TurkishPhone::type($phoneRaw),
            'website_raw' => $website,
            'website_status' => $this->classifier->classify($website),
            'rating' => $place['rating'] ?? null,
            'review_count' => $place['userRatingCount'] ?? 0,
            'types' => $place['types'] ?? [],
            'lat' => $place['location']['latitude'] ?? null,
            'lng' => $place['location']['longitude'] ?? null,
            'maps_url' => $place['googleMapsUri'] ?? null,
            'business_status' => $place['businessStatus'] ?? null,
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
            'status' => $channel === Business::CHANNEL_MANUAL
                ? Business::STATUS_MANUAL_CONTACT
                : Business::STATUS_DISCOVERED,
        ]);

        return true;
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
