<?php

namespace App\Services\Outreach;

use App\Models\Business;
use App\Models\MessageTemplate;
use App\Models\OutreachMessage;
use App\Models\Setting;
use Illuminate\Support\Str;

class MessageRenderer
{
    public static function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    /** Şablondan taslak mesaj oluşturur (onay bekler). */
    public function draft(Business $business, ?MessageTemplate $template = null): OutreachMessage
    {
        $channel = $business->contact_channel;
        $template ??= MessageTemplate::defaultFor($channel);

        $recipient = $channel === Business::CHANNEL_EMAIL ? $business->email : $business->phone;

        $message = new OutreachMessage([
            'business_id' => $business->id,
            'message_template_id' => $template?->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => OutreachMessage::STATUS_DRAFT,
            'body' => '',
        ]);
        $message->token = Str::random(32);

        $message->subject = $template?->subject ? $this->render($template->subject, $business, $message) : null;
        $message->body = $this->render($template?->body ?? '{firma_adi} için sitenizi hazırladık: {site_linki}', $business, $message);
        $message->save();

        return $message;
    }

    public function render(string $text, Business $business, OutreachMessage $message): string
    {
        $base = rtrim(config('app.url'), '/');

        $siteLink = $message->channel === Business::CHANNEL_EMAIL
            ? $base.'/t/c/'.$message->token
            : ($business->site?->url($message->token) ?? '');

        return strtr($text, [
            '{firma_adi}' => $business->name,
            '{site_linki}' => $siteLink,
            '{kurulum_ucreti}' => self::money($business->effectiveSetupPrice()),
            '{bakim_ucreti}' => self::money($business->effectiveMaintenancePrice()),
            '{ilce}' => $business->district?->name ?? '',
            '{il}' => $business->province?->name ?? '',
            '{ret_linki}' => $base.'/u/'.$message->token,
            '{gonderen_adi}' => (string) Setting::get('sender_name', config('app.name')),
            '{gonderen_telefon}' => (string) Setting::get('sender_phone', ''),
        ]);
    }
}
