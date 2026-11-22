<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cloudflare Workers AI (ücretsiz günlük kota) ile görsel üretir ve public diske kaydeder.
 */
class CloudflareImageGenerator
{
    public function isConfigured(): bool
    {
        return filled(config('leadgen.cloudflare.account_id')) && filled(config('leadgen.cloudflare.token'));
    }

    public function remainingToday(): int
    {
        return max(0, config('leadgen.cloudflare.daily_limit') - (int) Cache::get($this->quotaKey(), 0));
    }

    /** @return string kaydedilen dosyanın public disk yolu */
    public function generate(string $prompt, string $path): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Workers AI bilgileri tanımlı değil (.env).');
        }

        if ($this->remainingToday() <= 0) {
            throw new QuotaExceededException('Cloudflare günlük görsel kotası doldu.');
        }

        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
            config('leadgen.cloudflare.account_id'),
            config('leadgen.cloudflare.model'),
        );

        $response = Http::withToken(config('leadgen.cloudflare.token'))
            ->timeout(90)
            ->post($url, ['prompt' => $prompt, 'steps' => 6]);

        Cache::add($this->quotaKey(), 0, now()->endOfDay());
        Cache::increment($this->quotaKey());

        if ($response->status() === 429) {
            throw new QuotaExceededException('Cloudflare kota/limit hatası (429).');
        }

        if ($response->failed() || ! $response->json('success', true)) {
            throw new RuntimeException('Cloudflare görsel hatası: '.$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        $base64 = $response->json('result.image');

        if (! $base64 || ! ($binary = base64_decode($base64, true))) {
            throw new RuntimeException('Cloudflare geçerli görsel döndürmedi.');
        }

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function quotaKey(): string
    {
        return 'cf_images:'.now()->toDateString();
    }
}
