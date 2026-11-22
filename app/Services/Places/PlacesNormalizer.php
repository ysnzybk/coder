<?php

namespace App\Services\Places;

/**
 * Farklı kaynakları (Google Places yanıtı, dosya satırı) ortak "normalize satır" biçimine çevirir:
 * external_id, source, name, address, phone, website, email, social_url, rating, review_count,
 * types[], lat, lng, maps_url, business_status, confidence
 */
class PlacesNormalizer
{
    /** @param array<string, mixed> $place Places API (New) "place" nesnesi */
    public static function fromGooglePlace(array $place): array
    {
        return [
            'external_id' => $place['id'] ?? null,
            'source' => 'google',
            'name' => $place['displayName']['text'] ?? 'İsimsiz işletme',
            'address' => $place['formattedAddress'] ?? null,
            'phone' => $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null,
            'website' => $place['websiteUri'] ?? null,
            'email' => null,
            'social_url' => null,
            'rating' => $place['rating'] ?? null,
            'review_count' => $place['userRatingCount'] ?? 0,
            'types' => $place['types'] ?? [],
            'lat' => $place['location']['latitude'] ?? null,
            'lng' => $place['location']['longitude'] ?? null,
            'maps_url' => $place['googleMapsUri'] ?? null,
            'business_status' => $place['businessStatus'] ?? null,
            'confidence' => null,
        ];
    }

    /**
     * CSV/Overture satırı. Çok değerli alanlar (telefon, website, e-posta, sosyal) "|" veya ";" ile ayrılmış olabilir.
     *
     * @param  array<string, string>  $r  başlık → değer
     */
    public static function fromFileRow(array $r): array
    {
        $first = fn (?string $v) => ($v = trim((string) (preg_split('/[|;]/', (string) $v)[0] ?? ''))) !== '' ? $v : null;

        $status = mb_strtolower(trim($r['status'] ?? ''));

        return [
            'external_id' => ($id = trim($r['id'] ?? '')) !== '' ? 'file:'.$id : null,
            'source' => 'file',
            'name' => trim($r['name'] ?? '') ?: 'İsimsiz işletme',
            'address' => trim($r['address'] ?? '') ?: null,
            'phone' => $first($r['phone'] ?? null),
            'website' => $first($r['website'] ?? null),
            'email' => ($e = $first($r['email'] ?? null)) ? mb_strtolower($e) : null,
            'social_url' => $first($r['social'] ?? null),
            'rating' => null,
            'review_count' => 0,
            'types' => array_values(array_filter([trim($r['category'] ?? '')])),
            'lat' => is_numeric($r['lat'] ?? null) ? (float) $r['lat'] : null,
            'lng' => is_numeric($r['lng'] ?? null) ? (float) $r['lng'] : null,
            'maps_url' => null,
            'business_status' => match (true) {
                str_contains($status, 'permanent') => 'CLOSED_PERMANENTLY',
                str_contains($status, 'temporar') => 'CLOSED_TEMPORARILY',
                default => 'OPERATIONAL',
            },
            'confidence' => is_numeric($r['confidence'] ?? null) ? (float) $r['confidence'] : null,
        ];
    }
}
