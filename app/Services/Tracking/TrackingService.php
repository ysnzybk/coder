<?php

namespace App\Services\Tracking;

use App\Models\Business;
use App\Models\OutreachMessage;
use App\Models\TrackingEvent;
use Illuminate\Http\Request;

class TrackingService
{
    /**
     * Link önizleme botları (WhatsApp, Telegram, Facebook, Gmail proxy vb.) gerçek ziyaret sayılmaz.
     */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|whatsapp|facebookexternalhit|telegram|skype|slack|discord|headless|curl|wget|python|go-http|java\//i';

    public function isBot(?string $userAgent): bool
    {
        return $userAgent === null || $userAgent === '' || (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    public function record(Request $request, string $type, Business $business, ?OutreachMessage $message = null): ?TrackingEvent
    {
        // Admin kendi önizlemesini açtığında sayılmaz
        if ($request->user() !== null) {
            return null;
        }

        $ua = $request->userAgent();

        // Gmail görselleri proxy üzerinden yükler (UA "GoogleImageProxy"); açılma olarak sayılır.
        $isGmailProxy = $ua && str_contains($ua, 'GoogleImageProxy');

        if (! $isGmailProxy && $this->isBot($ua)) {
            return null;
        }

        $event = TrackingEvent::create([
            'business_id' => $business->id,
            'outreach_message_id' => $message?->id,
            'type' => $type,
            'channel' => $message?->channel,
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
            'user_agent' => mb_substr((string) $ua, 0, 500),
        ]);

        if ($message) {
            if ($type === TrackingEvent::TYPE_EMAIL_OPEN && ! $message->first_opened_at) {
                $message->update(['first_opened_at' => now()]);
            }
            if ($type === TrackingEvent::TYPE_EMAIL_CLICK && ! $message->first_clicked_at) {
                $message->update(['first_clicked_at' => now()]);
            }
        }

        if ($type === TrackingEvent::TYPE_SITE_VIEW && $message) {
            $business->advanceTo(Business::STATUS_VIEWED);
        }

        return $event;
    }
}
