<?php

namespace App\Services\Images;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cloudflare Workers AI ücretsiz günlük 10.000 neuron havuzunu (metin + görsel ortak) korur.
 * Sayaç UTC gününe göre tutulur (Cloudflare 00:00 UTC'de sıfırlar). Değerler tahmindir;
 * Cloudflare kendi sayacıyla reddederse (429/hata) iş yine ertelenir, ücret çıkmaz.
 */
class CloudflareBudget
{
    public function budget(): int
    {
        return (int) config('leadgen.cloudflare.daily_neuron_budget');
    }

    public function used(): int
    {
        return (int) Cache::get($this->key(), 0);
    }

    public function remaining(): int
    {
        return max(0, $this->budget() - $this->used());
    }

    public function canSpend(float $neurons): bool
    {
        return $this->used() + (int) ceil($neurons) <= $this->budget();
    }

    public function spend(float $neurons): void
    {
        Cache::add($this->key(), 0, $this->resetAt());
        Cache::increment($this->key(), (int) ceil($neurons));
    }

    /** Bütçe dolu ise ya da cloudflare 429 verirse kalan günün tamamını harcanmış say. */
    public function exhaust(): void
    {
        Cache::add($this->key(), 0, $this->resetAt());
        Cache::put($this->key(), $this->budget(), $this->resetAt());
    }

    public function secondsUntilReset(): int
    {
        return max(60, (int) now()->diffInSeconds($this->resetAt(), true) + 120); // 2 dk pay
    }

    public function imageCost(): float
    {
        return (float) config('leadgen.cloudflare.neurons_per_image');
    }

    public function textCost(int $inputTokens, int $outputTokens): float
    {
        return $inputTokens / 1000 * config('leadgen.cloudflare.neurons_per_1k_input_tokens')
            + $outputTokens / 1000 * config('leadgen.cloudflare.neurons_per_1k_output_tokens');
    }

    private function key(): string
    {
        return 'cf_neurons:'.Carbon::now('UTC')->toDateString();
    }

    private function resetAt(): Carbon
    {
        return Carbon::now('UTC')->addDay()->startOfDay();
    }
}
