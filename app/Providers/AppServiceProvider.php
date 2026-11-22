<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Ai\ClaudeContentGenerator;
use App\Services\Ai\CloudflareContentGenerator;
use App\Services\Ai\SiteContentGenerator;
use App\Services\Ai\TemplateContentGenerator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(apiKey: config('leadgen.anthropic.key')));

        // LEADGEN_AI_PROVIDER: cloudflare (ücretsiz, varsayılan) | anthropic (ücretli) | template.
        // Seçilen sağlayıcının anahtarı yoksa şablon içerikle çalışır (demo / anahtar gelene kadar).
        $this->app->bind(SiteContentGenerator::class, function ($app) {
            $cloudflare = filled(config('leadgen.cloudflare.account_id')) && filled(config('leadgen.cloudflare.token'));

            return match (config('leadgen.ai_provider')) {
                'cloudflare' => $cloudflare ? $app->make(CloudflareContentGenerator::class) : $app->make(TemplateContentGenerator::class),
                'anthropic' => filled(config('leadgen.anthropic.key')) ? $app->make(ClaudeContentGenerator::class) : $app->make(TemplateContentGenerator::class),
                default => $app->make(TemplateContentGenerator::class),
            };
        });
    }

    public function boot(): void
    {
        // Canlıda (APP_URL https) üretilen tüm bağlantılar ve varlıklar https olsun
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
