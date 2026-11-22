<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Services\Images\CloudflareBudget;
use App\Services\Images\CloudflareBudgetExceeded;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Workers AI (Llama, ücretsiz günlük neuron havuzu) ile site içeriği üretir.
 * JSON şema modu (response_format=json_schema) kullanır; yapı/uzunluk/içerik denetiminden geçirir.
 * Başarısız olursa bir kez yeniden dener, ardından şablon içeriğe düşer. Bütçe dolarsa
 * CloudflareBudgetExceeded fırlatır (iş ertesi UTC gününe ertelenir, şablona düşülmez).
 */
class CloudflareContentGenerator implements SiteContentGenerator
{
    public function __construct(
        private readonly TemplateContentGenerator $fallback,
        private readonly ContentSanitizer $sanitizer,
        private readonly CloudflareBudget $budget,
    ) {}

    public function generate(Business $business): array
    {
        $template = array_diff_key($this->fallback->generate($business), ['_source' => true]);
        $system = SitePrompt::system()."\n\nYanıtı yalnızca verilen JSON şemasına uygun, geçerli bir JSON nesnesi olarak ver.";
        $user = SitePrompt::brief($business);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $estimate = $this->budget->textCost((int) ((mb_strlen($system.$user)) / 3), (int) (config('leadgen.cloudflare.text_max_tokens') * 0.6));

            if (! $this->budget->canSpend($estimate)) {
                throw new CloudflareBudgetExceeded($this->budget->secondsUntilReset());
            }

            $json = $this->request($system, $user);

            if (is_array($json) && ($clean = $this->sanitizer->clean($json)) && $this->usable($clean)) {
                return ['_source' => 'cloudflare'] + ContentSchema::normalize($clean, $template);
            }

            Log::info('Cloudflare içerik yanıtı kullanılamadı', ['business' => $business->id, 'attempt' => $attempt]);
        }

        return $template + ['_source' => 'template'];
    }

    /** En azından başlık, hakkımızda ve hizmetler dolu olmalı; aksi halde şablon daha güvenli. */
    private function usable(array $clean): bool
    {
        return ! empty($clean['about']) && count($clean['services'] ?? []) >= 3 && ! empty($clean['hero_subtitle']);
    }

    private function request(string $system, string $user): ?array
    {
        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
            config('leadgen.cloudflare.account_id'),
            config('leadgen.cloudflare.text_model'),
        );

        $response = Http::withToken(config('leadgen.cloudflare.token'))
            ->timeout(120)
            ->post($url, [
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ContentSchema::schema()],
                'max_tokens' => config('leadgen.cloudflare.text_max_tokens'),
                'temperature' => 0.3,
            ]);

        $errors = collect($response->json('errors', []));

        if ($response->status() === 429 || $errors->contains(fn ($e) => str_contains(mb_strtolower(json_encode($e)), 'neuron') || str_contains(mb_strtolower(json_encode($e)), 'daily free allocation'))) {
            $this->budget->exhaust();

            throw new CloudflareBudgetExceeded($this->budget->secondsUntilReset(), 'Cloudflare kota/limit hatası.');
        }

        if ($response->failed() || ! $response->json('success', true)) {
            // "JSON Mode couldn't be met" dahil: bu deneme kullanılamaz, üst katman yeniden dener.
            Log::warning('Cloudflare metin isteği başarısız', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        $usage = $response->json('result.usage', []);
        // Gerçek tüketim yanıtta gelir (usage.neurons); yoksa token sayısından tahmin edilir.
        $this->budget->spend((float) ($usage['neurons'] ?? $this->budget->textCost((int) ($usage['prompt_tokens'] ?? 1500), (int) ($usage['completion_tokens'] ?? config('leadgen.cloudflare.text_max_tokens') * 0.6))));

        // Yanıt biçimi modele göre değişir: eski biçim result.response, OpenAI uyumlu biçim result.choices[0].message.content
        $result = $response->json('result.response') ?? $response->json('result.choices.0.message.content');

        if (is_string($result)) {
            $result = json_decode($result, true) ?? $this->extractJson($result);
        }

        return is_array($result) ? $result : null;
    }

    /** Model JSON'u ```json bloğu ya da ek metinle sarmışsa ayıkla. */
    private function extractJson(string $text): ?array
    {
        if (preg_match('/\{.*\}/su', $text, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
