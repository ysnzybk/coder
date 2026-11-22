<?php

namespace App\Http\Controllers;

use App\Models\OutreachMessage;
use App\Models\Site;
use App\Models\TrackingEvent;
use App\Services\Phone\TurkishPhone;
use App\Services\Sites\Themes;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    /** Satış sonrası firmanın kendi alan adı bu uygulamaya yönlendirildiğinde ana sayfa. */
    public function home(Request $request)
    {
        $host = preg_replace('/^www\./', '', mb_strtolower($request->getHost()));
        $site = Site::where('custom_domain', $host)->where('is_active', true)->first();

        return $site ? $this->render($request, $site) : redirect('/admin');
    }

    public function show(Request $request, string $slug)
    {
        $site = Site::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->render($request, $site);
    }

    private function render(Request $request, Site $site)
    {
        $site->load('business.district', 'business.province', 'business.category');
        $business = $site->business;

        $message = $request->query('r')
            ? OutreachMessage::where('token', $request->query('r'))->where('business_id', $business->id)->first()
            : null;

        $this->tracking->record($request, TrackingEvent::TYPE_SITE_VIEW, $business, $message);

        $content = $site->content ?? [];

        return response()->view('sites.show', [
            'site' => $site,
            'business' => $business,
            'c' => $content,
            'palette' => Themes::palette($content['palette'] ?? null),
            'font' => Themes::font($content['font_style'] ?? null),
            'labels' => Themes::labels($site->template),
            'images' => $site->imageUrls(),
            'phoneDisplay' => TurkishPhone::display($business->phone),
            'phoneTel' => TurkishPhone::normalize($business->phone),
            'whatsApp' => TurkishPhone::isMobile($business->phone) ? TurkishPhone::forWhatsApp($business->phone) : null,
            'isPreview' => ! $site->is_sold,
            'optOutToken' => $message?->token,
        ])->header('X-Robots-Tag', $site->is_sold ? 'all' : 'noindex, nofollow');
    }
}
