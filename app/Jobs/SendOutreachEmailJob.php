<?php

namespace App\Jobs;

use App\Mail\OutreachMail;
use App\Models\Business;
use App\Models\OptOut;
use App\Models\OutreachMessage;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Onaylı e-postayı SMTP limitlerine uyarak gönderir. */
class SendOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    public function __construct(public int $messageId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(7);
    }

    public function handle(): void
    {
        $message = OutreachMessage::with('business.site')->findOrFail($this->messageId);

        // Onaysız hiçbir mesaj gönderilmez.
        if (! $message->isSendable()) {
            return;
        }

        if (OptOut::matches(null, $message->recipient)) {
            $message->update(['status' => OutreachMessage::STATUS_REJECTED, 'error' => 'Ret listesinde']);

            return;
        }

        if ($delay = $this->rateLimitDelay()) {
            $this->release($delay);

            return;
        }

        Mail::to($message->recipient)->send(new OutreachMail($message));

        $message->update(['status' => OutreachMessage::STATUS_SENT, 'sent_at' => now(), 'error' => null]);
        $message->business->advanceTo(Business::STATUS_SENT);
    }

    /** Saatlik/günlük limit dolmuşsa beklenecek saniye. */
    private function rateLimitDelay(): int
    {
        $perHour = (int) Setting::get('mail_per_hour', config('leadgen.mail.per_hour'));
        $perDay = (int) Setting::get('mail_per_day', config('leadgen.mail.per_day'));

        $sent = fn ($since) => OutreachMessage::where('channel', Business::CHANNEL_EMAIL)
            ->where('status', OutreachMessage::STATUS_SENT)
            ->where('sent_at', '>=', $since)
            ->count();

        if ($sent(now()->startOfDay()) >= $perDay) {
            return (int) now()->diffInSeconds(now()->addDay()->startOfDay()->addMinutes(10), true);
        }

        if ($sent(now()->subHour()) >= $perHour) {
            return 15 * 60;
        }

        return 0;
    }

    public function failed(Throwable $e): void
    {
        OutreachMessage::whereKey($this->messageId)->update([
            'status' => OutreachMessage::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
