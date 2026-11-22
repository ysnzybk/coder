<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Site;
use App\Services\Ai\SiteContentGenerator;
use App\Services\Images\CloudflareBudgetExceeded;
use App\Services\Places\BusinessImporter;
use App\Services\Places\PlacesBudgetExceeded;
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

    /** Gerçek hatalarda en fazla 3 deneme; ücretsiz kota beklemeleri (release) bu sayıya girmez. */
    public int $maxExceptions = 3;

    public array $backoff = [60, 300];

    public int $timeout = 240;

    public function __construct(public int $businessId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(3);
    }

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

        if ($business->reviews === null && $business->source === 'google' && $places->isConfigured()) {
            try {
                $importer->enrich($business);
                $business->refresh();
            } catch (PlacesBudgetExceeded) {
                // Aylık ücretsiz Places kotası doldu: yorum/saat olmadan devam (sonra "yeniden üret" ile tamamlanabilir).
            }
        }

        try {
            $content = $generator->generate($business);
        } catch (CloudflareBudgetExceeded $e) {
            // Ücretsiz günlük neuron havuzu doldu: UTC 00:00 sonrasına ertele (hata sayılmaz).
            $this->release($e->secondsUntilReset);

            return;
        }

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
