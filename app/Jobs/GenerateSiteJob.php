<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Site;
use App\Services\Ai\SiteContentGenerator;
use App\Services\Places\BusinessImporter;
use App\Services\Places\PlacesClient;
use App\Services\Sites\SlugGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** 1. adım: yorumları çek → Claude içerik → site kaydı. Ardından görsel işini başlatır. */
class GenerateSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 240;

    public function __construct(public int $businessId) {}

    public function handle(
        SiteContentGenerator $generator,
        SlugGenerator $slugs,
        BusinessImporter $importer,
        PlacesClient $places,
    ): void {
        $business = Business::with(['category', 'district', 'province', 'site'])->findOrFail($this->businessId);

        if ($business->isOptedOut()) {
            $business->update(['status' => Business::STATUS_OPTED_OUT]);

            return;
        }

        $business->update(['status' => Business::STATUS_GENERATING, 'last_error' => null]);

        if ($business->reviews === null && $places->isConfigured()) {
            $importer->enrich($business);
            $business->refresh();
        }

        $content = $generator->generate($business);

        $site = $business->site ?? new Site([
            'business_id' => $business->id,
            'slug' => $slugs->for($business),
        ]);

        $site->fill([
            'template' => $business->category?->template ?? 'genel',
            'content' => $content,
            'palette' => $content['palette'],
            'generated_at' => now(),
            'is_active' => true,
        ])->save();

        GenerateSiteImagesJob::dispatch($site->id);
    }

    public function failed(Throwable $e): void
    {
        Business::whereKey($this->businessId)->update([
            'status' => Business::STATUS_FAILED,
            'last_error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
