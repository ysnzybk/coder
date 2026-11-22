<?php

namespace App\Services\Places;

use App\Models\ApiUsage;
use Illuminate\Support\Carbon;

/**
 * Google Places ücretsiz aylık kotasının (SKU başına ≈ 1.000 Enterprise istek) altında kalmak için
 * uygulama içi sayaç. Google'ın kendi sayacı esastır; Cloud Console'da ayrıca günlük kota kapağı koyun.
 * Google faturalama ayı Pasifik saatine göre işler.
 */
class PlacesBudget
{
    public const TEXT = 'places_text_search';

    public const DETAILS = 'places_details';

    public function period(): string
    {
        return Carbon::now('America/Los_Angeles')->format('Y-m');
    }

    public function limit(string $key): int
    {
        return (int) config($key === self::TEXT ? 'leadgen.places.monthly_text_limit' : 'leadgen.places.monthly_details_limit');
    }

    public function used(string $key): int
    {
        return ApiUsage::count($this->period(), $key);
    }

    public function remaining(string $key): ?int
    {
        $limit = $this->limit($key);

        return $limit > 0 ? max(0, $limit - $this->used($key)) : null;
    }

    /** @throws PlacesBudgetExceeded */
    public function assertAvailable(string $key): void
    {
        $limit = $this->limit($key);

        if ($limit > 0 && $this->used($key) >= $limit) {
            throw new PlacesBudgetExceeded(sprintf('Google Places aylık ücretsiz kota sınırı doldu (%s: %d/%d). Sınır yeni ayda sıfırlanır.', $key, $this->used($key), $limit));
        }
    }

    public function record(string $key): void
    {
        ApiUsage::hit($this->period(), $key);
    }
}
