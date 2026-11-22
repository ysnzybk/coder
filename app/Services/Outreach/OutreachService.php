<?php

namespace App\Services\Outreach;

use App\Jobs\SendOutreachEmailJob;
use App\Models\Business;
use App\Models\Deal;
use App\Models\OptOut;
use App\Models\OutreachMessage;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutreachService
{
    public function __construct(private readonly WhatsAppLinkBuilder $whatsApp) {}

    public function approve(OutreachMessage $message): void
    {
        if ($message->status !== OutreachMessage::STATUS_DRAFT) {
            return;
        }

        if ($message->business->isOptedOut()) {
            $message->update(['status' => OutreachMessage::STATUS_REJECTED, 'error' => 'Ret listesinde']);

            return;
        }

        $isEmail = $message->channel === Business::CHANNEL_EMAIL;

        $message->update([
            'status' => $isEmail ? OutreachMessage::STATUS_QUEUED : OutreachMessage::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $message->business->advanceTo(Business::STATUS_APPROVED);

        if ($isEmail) {
            SendOutreachEmailJob::dispatch($message->id);
        }
    }

    public function reject(OutreachMessage $message): void
    {
        if (in_array($message->status, [OutreachMessage::STATUS_DRAFT, OutreachMessage::STATUS_APPROVED, OutreachMessage::STATUS_QUEUED], true)) {
            $message->update(['status' => OutreachMessage::STATUS_REJECTED]);
        }
    }

    /**
     * WhatsApp: mesaj onaylı olmalı. "Gönderildi" işaretler ve wa.me linkini döndürür
     * (gönderimi kullanıcı kendi telefonundan tamamlar).
     */
    public function openWhatsApp(OutreachMessage $message): string
    {
        if ($message->channel !== Business::CHANNEL_WHATSAPP) {
            throw new RuntimeException('Bu mesaj WhatsApp mesajı değil.');
        }

        if (! in_array($message->status, [OutreachMessage::STATUS_APPROVED, OutreachMessage::STATUS_SENT], true)) {
            throw new RuntimeException('Mesaj henüz onaylanmadı.');
        }

        if ($message->business->isOptedOut()) {
            throw new RuntimeException('Firma ret listesinde; mesaj gönderilemez.');
        }

        $link = $this->whatsApp->build($message) ?? throw new RuntimeException('Geçerli WhatsApp numarası yok.');

        if ($message->status !== OutreachMessage::STATUS_SENT) {
            $message->update(['status' => OutreachMessage::STATUS_SENT, 'sent_at' => now()]);
            $message->business->advanceTo(Business::STATUS_SENT);
        }

        return $link;
    }

    public function optOut(OutreachMessage $message, string $source = 'link'): void
    {
        $business = $message->business;

        OptOut::register($business->phone, $business->email ?? ($message->channel === Business::CHANNEL_EMAIL ? $message->recipient : null), $source);

        $business->update(['status' => Business::STATUS_OPTED_OUT]);
        $business->outreachMessages()
            ->whereIn('status', [OutreachMessage::STATUS_DRAFT, OutreachMessage::STATUS_APPROVED, OutreachMessage::STATUS_QUEUED])
            ->update(['status' => OutreachMessage::STATUS_REJECTED]);
    }

    /** Satış kaydı: firma "won", site satıldı, kurulum ödemesi oluşturulur. */
    public function markWon(Business $business, int $setupPrice, int $maintenancePrice, ?string $startDate = null): Deal
    {
        return DB::transaction(function () use ($business, $setupPrice, $maintenancePrice, $startDate) {
            $deal = Deal::updateOrCreate(['business_id' => $business->id], [
                'setup_price' => $setupPrice,
                'maintenance_price' => $maintenancePrice,
                'started_at' => $startDate ?? now()->toDateString(),
                'status' => 'active',
                'cancelled_at' => null,
            ]);

            Payment::firstOrCreate(
                ['deal_id' => $deal->id, 'type' => 'setup', 'period' => null],
                ['amount' => $setupPrice, 'due_date' => $deal->started_at],
            );

            $business->update(['status' => Business::STATUS_WON]);
            $business->site?->update(['is_sold' => true]);

            return $deal;
        });
    }

    /**
     * Aktif satışlar için bakım ödemelerini oluşturur (zamanlanmış görev, günlük).
     * Bakım, satıştan sonraki aydan başlar; zamanlayıcı çalışmadığı günler/aylar olduysa kaçan dönemler de tamamlanır.
     */
    public function generateMaintenancePayments(): int
    {
        $created = 0;
        $current = now()->startOfMonth();

        Deal::where('status', 'active')->each(function (Deal $deal) use ($current, &$created) {
            if ($deal->maintenance_price <= 0) {
                return;
            }

            $end = $deal->cancelled_at ? $deal->cancelled_at->copy()->startOfMonth() : $current;

            for ($month = $deal->started_at->copy()->startOfMonth()->addMonth(); $month->lte($end) && $month->lte($current); $month->addMonth()) {
                $payment = Payment::firstOrCreate(
                    ['deal_id' => $deal->id, 'type' => 'maintenance', 'period' => $month->format('Y-m')],
                    ['amount' => $deal->maintenance_price, 'due_date' => $month->copy()->addDays(min(27, $deal->started_at->day - 1))],
                );

                $created += $payment->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $created;
    }
}
