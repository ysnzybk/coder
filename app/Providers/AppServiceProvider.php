<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Ai\ClaudeContentGenerator;
use App\Services\Ai\SiteContentGenerator;
use App\Services\Ai\TemplateContentGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(apiKey: config('leadgen.anthropic.key')));

        // ANTHROPIC_API_KEY yoksa şablon içerikle çalışır (demo / anahtar gelene kadar).
        $this->app->bind(SiteContentGenerator::class, fn ($app) => filled(config('leadgen.anthropic.key'))
            ? $app->make(ClaudeContentGenerator::class)
            : $app->make(TemplateContentGenerator::class));
    }

    public function boot(): void
    {
        //
    }
}
