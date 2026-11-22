<?php

namespace App\Services\Places;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Places API (New) istemcisi.
 *
 * Maliyet kontrolü: arama aşamasında yorum/çalışma saati istenmez; bu alanlar
 * yalnızca "sitesi yok" olarak uygun bulunan firmalar için details() ile çekilir.
 */
class PlacesClient
{
    public int $calls = 0;

    private const SEARCH_FIELDS = [
        'places.id', 'places.displayName', 'places.formattedAddress',
        'places.nationalPhoneNumber', 'places.internationalPhoneNumber',
        'places.websiteUri', 'places.rating', 'places.userRatingCount',
        'places.types', 'places.primaryType', 'places.location', 'places.googleMapsUri',
        'places.businessStatus', 'nextPageToken',
    ];

    private const DETAIL_FIELDS = [
        'id', 'reviews', 'regularOpeningHours', 'editorialSummary',
    ];

    public function isConfigured(): bool
    {
        return filled(config('leadgen.places.key'));
    }

    /**
     * @return array<int, array<string, mixed>> Places API "place" nesneleri
     */
    public function searchText(string $query, ?string $includedType = null, ?array $bias = null): array
    {
        $places = [];
        $pageToken = null;
        $maxPages = max(1, config('leadgen.places.max_pages'));

        for ($page = 0; $page < $maxPages; $page++) {
            $body = array_filter([
                'textQuery' => $query,
                'languageCode' => config('leadgen.places.language'),
                'regionCode' => config('leadgen.places.region'),
                'pageSize' => 20,
                'pageToken' => $pageToken,
                'includedType' => $includedType ?: null,
                'locationBias' => $bias ? ['circle' => [
                    'center' => ['latitude' => $bias['lat'], 'longitude' => $bias['lng']],
                    'radius' => $bias['radius'] ?? 5000.0,
                ]] : null,
            ]);

            $response = $this->request(self::SEARCH_FIELDS)->post('/places:searchText', $body);
            $this->calls++;

            if ($response->failed()) {
                throw new RuntimeException('Places arama hatası: '.$response->status().' '.$response->body());
            }

            $places = array_merge($places, $response->json('places', []));
            $pageToken = $response->json('nextPageToken');

            if (! $pageToken) {
                break;
            }
        }

        return $places;
    }

    /** @return array<string, mixed> */
    public function details(string $placeId): array
    {
        $response = $this->request(self::DETAIL_FIELDS)->get('/places/'.urlencode($placeId));
        $this->calls++;

        if ($response->failed()) {
            throw new RuntimeException('Places detay hatası: '.$response->status().' '.$response->body());
        }

        return $response->json();
    }

    private function request(array $fields): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GOOGLE_PLACES_API_KEY tanımlı değil (.env).');
        }

        return Http::baseUrl(config('leadgen.places.base_url'))
            ->withHeaders([
                'X-Goog-Api-Key' => config('leadgen.places.key'),
                'X-Goog-FieldMask' => implode(',', $fields),
            ])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 1000, throw: false);
    }
}
