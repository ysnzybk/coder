<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cloudflare Workers AI (ücretsiz günlük neuron havuzu) ile görsel üretir ve public diske kaydeder.
 */
class CloudflareImageGenerator
{
    public function __construct(private readonly CloudflareBudget $budget) {}

    public function isConfigured(): bool
    {
        return filled(config('leadgen.cloudflare.account_id')) && filled(config('leadgen.cloudflare.token'));
    }

    public function remainingToday(): int
    {
        return $this->budget->remaining();
    }

    /** @return string kaydedilen dosyanın public disk yolu */
    public function generate(string $prompt, string $path, ?int $steps = null): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Workers AI bilgileri tanımlı değil (.env).');
        }

        $steps ??= (int) config('leadgen.cloudflare.image_steps');

        if (! $this->budget->canSpend($this->budget->imageCost($steps))) {
            throw new CloudflareBudgetExceeded($this->budget->secondsUntilReset());
        }

        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
            config('leadgen.cloudflare.account_id'),
            config('leadgen.cloudflare.model'),
        );

        $response = Http::withToken(config('leadgen.cloudflare.token'))
            ->timeout(90)
            ->post($url, ['prompt' => $prompt, 'steps' => $steps]);

        if ($response->status() === 429 || $this->isNeuronLimitError($response->json('errors', []))) {
            $this->budget->exhaust();

            throw new CloudflareBudgetExceeded($this->budget->secondsUntilReset(), 'Cloudflare kota/limit hatası.');
        }

        if ($response->failed() || ! $response->json('success', true)) {
            throw new RuntimeException('Cloudflare görsel hatası: '.$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        // Gerçek tüketim yanıtta gelir (result.usage.neurons); yoksa formülle tahmin edilir.
        $this->budget->spend((float) ($response->json('result.usage.neurons') ?? $this->budget->imageCost($steps)));

        $base64 = $response->json('result.image');

        if (! $base64 || ! ($binary = base64_decode($base64, true))) {
            throw new RuntimeException('Cloudflare geçerli görsel döndürmedi.');
        }

        Storage::disk('public')->put($path, $this->optimize($binary));

        return $path;
    }

    /** Ham 1024x1024 JPEG (~870 KB) mobil sayfa için ağır: genişliği sınırla ve yeniden sıkıştır. */
    private function optimize(string $binary): string
    {
        if (! function_exists('imagecreatefromstring') || ! ($image = @imagecreatefromstring($binary))) {
            return $binary;
        }

        $max = (int) config('leadgen.cloudflare.image_max_width');

        if ($max > 0 && imagesx($image) > $max && ($scaled = imagescale($image, $max, -1, IMG_BICUBIC))) {
            $image = $scaled;
        }

        ob_start();
        imagejpeg($image, null, (int) config('leadgen.cloudflare.image_quality'));
        $out = ob_get_clean();

        return $out !== false && $out !== '' ? $out : $binary;
    }

    private function isNeuronLimitError(array $errors): bool
    {
        return collect($errors)->contains(fn ($e) => str_contains(mb_strtolower(json_encode($e)), 'neuron') || str_contains(mb_strtolower(json_encode($e)), 'daily free allocation'));
    }
}
