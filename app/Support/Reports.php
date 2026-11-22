<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Deal;
use App\Models\OutreachMessage;
use App\Models\Payment;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Dashboard filtreleri (il, ilçe, sektör, tarih) ile rapor sorguları. */
class Reports
{
    public function __construct(private array $filters = []) {}

    public function businesses(): Builder
    {
        $f = $this->filters;

        return Business::query()
            ->when($f['province_id'] ?? null, fn ($q, $v) => $q->where('province_id', $v))
            ->when($f['district_ids'] ?? null, fn ($q, $v) => $q->whereIn('district_id', (array) $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['until'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));
    }

    private function ids(): Builder
    {
        return $this->businesses()->select('id');
    }

    /** Huni: her aşamaya ulaşmış firma sayısı */
    public function funnel(): array
    {
        $ids = fn () => $this->ids();

        return [
            'Bulunan' => $this->businesses()->count(),
            'Hedef (sitesi yok)' => $this->businesses()->whereNotIn('status', [Business::STATUS_DISQUALIFIED])->count(),
            'Site üretildi' => $this->businesses()->whereHas('site')->count(),
            'Mesaj gönderildi' => OutreachMessage::whereIn('business_id', $ids())->where('status', 'sent')->distinct()->count('business_id'),
            'Site açıldı' => TrackingEvent::whereIn('business_id', $ids())->where('type', 'site_view')->whereNotNull('outreach_message_id')->distinct()->count('business_id'),
            'Cevap / ilgili' => $this->businesses()->where(fn ($q) => $q->whereIn('status', [Business::STATUS_REPLIED, Business::STATUS_WON])->orWhere('call_outcome', 'interested'))->count(),
            'Satış' => Deal::whereIn('business_id', $ids())->count(),
        ];
    }

    public function channelStats(): array
    {
        $ids = $this->ids();
        $sent = fn (string $ch) => OutreachMessage::whereIn('business_id', $this->ids())->where('channel', $ch)->where('status', 'sent');

        $emailSent = $sent('email')->count();
        $emailOpened = $sent('email')->whereNotNull('first_opened_at')->count();
        $emailClicked = $sent('email')->whereNotNull('first_clicked_at')->count();
        $waSent = $sent('whatsapp')->count();
        $waViewed = $sent('whatsapp')->whereExists(fn ($q) => $q->selectRaw(1)->from('tracking_events')
            ->whereColumn('tracking_events.outreach_message_id', 'outreach_messages.id')->where('type', 'site_view'))->count();

        return compact('emailSent', 'emailOpened', 'emailClicked', 'waSent', 'waViewed');
    }

    public function revenue(): array
    {
        $deals = Deal::whereIn('business_id', $this->ids());
        $dealIds = (clone $deals)->select('id');

        return [
            'deals' => (clone $deals)->count(),
            'mrr' => (int) (clone $deals)->where('status', 'active')->sum('maintenance_price'),
            'setup_collected' => (int) Payment::whereIn('deal_id', $dealIds)->where('type', 'setup')->whereNotNull('paid_at')->sum('amount'),
            'collected_this_month' => (int) Payment::whereIn('deal_id', (clone $deals)->select('id'))->whereNotNull('paid_at')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'outstanding' => (int) Payment::whereIn('deal_id', (clone $deals)->select('id'))->whereNull('paid_at')->sum('amount'),
        ];
    }

    public static function rate(int $part, int $whole): string
    {
        return $whole > 0 ? '%'.number_format($part / $whole * 100, 1, ',', '.') : '—';
    }
}
