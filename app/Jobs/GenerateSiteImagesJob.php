<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Site;
use App\Services\Images\CloudflareBudgetExceeded;
use App\Services\Images\CloudflareImageGenerator;
use App\Services\Outreach\MessageRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 2. adım: Cloudflare ile görselleri üretir, siteyi "hazır" yapar ve onay için taslak mesaj oluşturur.
 * Kota dolarsa yarın tekrar dener; Cloudflare tanımlı değilse görselsiz (degrade arka planla) devam eder.
 */
class GenerateSiteImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /** Gerçek hatalarda en fazla 3 deneme (kota beklemeleri sayılmaz). */
    public int $maxExceptions = 3;

    /** @param bool $skipImages true: kalan görseller beklenmeden site hazır işaretlenir ("Görselsiz hazırla"). */
    public function __construct(public int $siteId, public bool $skipImages = false) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(3);
    }

    public function handle(CloudflareImageGenerator $images, MessageRenderer $renderer): void
    {
        $site = Site::with('business.site')->findOrFail($this->siteId);
        $business = $site->business;

        if ($images->isConfigured() && ! $this->skipImages) {
            $paths = $site->images ?? [];
            $prompts = array_slice($site->content['image_prompts'] ?? [], 0, config('leadgen.cloudflare.images_per_site'), true);

            foreach ($prompts as $key => $prompt) {
                if (isset($paths[$key])) {
                    continue; // daha önce üretilmiş (yeniden denemede kota harcanmaz)
                }

                try {
                    $paths[$key] = $images->generate($prompt, "sites/{$site->slug}/{$key}.jpg");
                    $site->update(['images' => $paths]);
                } catch (CloudflareBudgetExceeded $e) {
                    Log::info("Cloudflare günlük neuron bütçesi doldu, site {$site->id} sıfırlanmadan sonra tamamlanacak.");
                    $this->release($e->secondsUntilReset);

                    return;
                }
            }
        }

        $this->finish($business, $renderer);
    }

    private function finish(Business $business, MessageRenderer $renderer): void
    {
        $business->refresh();

        if (in_array($business->status, [Business::STATUS_GENERATING, Business::STATUS_FAILED, Business::STATUS_DISCOVERED, Business::STATUS_MANUAL_CONTACT], true)) {
            $business->update(['status' => Business::STATUS_SITE_READY]);
        }

        // Manuel kanalda mesaj yok; diğerlerinde onay bekleyen tek bir taslak oluşturulur.
        $hasPending = $business->outreachMessages()->whereIn('status', ['draft', 'approved', 'queued'])->exists();

        if ($business->contact_channel !== Business::CHANNEL_MANUAL && ! $hasPending) {
            $renderer->draft($business->load('site', 'district', 'province', 'category'));
        }
    }

    public function failed(Throwable $e): void
    {
        $site = Site::find($this->siteId);
        $site?->business?->update(['last_error' => 'Görsel: '.mb_substr($e->getMessage(), 0, 1900)]);

        // Görsel başarısız olsa da site görselsiz kullanılabilir.
        if ($site) {
            $this->finish($site->business, app(MessageRenderer::class));
        }
    }
}
