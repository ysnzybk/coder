<?php

namespace App\Http\Controllers;

use App\Models\OutreachMessage;
use App\Models\TrackingEvent;
use App\Services\Outreach\OutreachService;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    // 1x1 şeffaf GIF
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private readonly TrackingService $tracking) {}

    public function open(Request $request, string $token)
    {
        if ($message = OutreachMessage::with('business')->where('token', $token)->first()) {
            $this->tracking->record($request, TrackingEvent::TYPE_EMAIL_OPEN, $message->business, $message);
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /** Yalnızca kendi sitemize yönlendirir (açık yönlendirme yok). */
    public function click(Request $request, string $token)
    {
        $message = OutreachMessage::with('business.site')->where('token', $token)->firstOrFail();
        $site = $message->business->site;

        abort_unless($site, 404);

        $this->tracking->record($request, TrackingEvent::TYPE_EMAIL_CLICK, $message->business, $message);

        return redirect()->away($site->url($message->token));
    }

    /** Ret sayfası: link tarayıcı/önizleme botlarının yanlışlıkla ret vermemesi için onay butonu ister. */
    public function optOutForm(string $token)
    {
        $message = OutreachMessage::with('business')->where('token', $token)->firstOrFail();

        return view('sites.opt-out', ['message' => $message, 'done' => false]);
    }

    public function optOut(Request $request, string $token, OutreachService $outreach)
    {
        $message = OutreachMessage::with('business')->where('token', $token)->firstOrFail();

        $outreach->optOut($message);

        TrackingEvent::create([
            'business_id' => $message->business_id,
            'outreach_message_id' => $message->id,
            'type' => TrackingEvent::TYPE_OPT_OUT,
            'channel' => $message->channel,
        ]);

        return view('sites.opt-out', ['message' => $message, 'done' => true]);
    }
}
