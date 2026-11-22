<?php

namespace App\Mail;

use App\Models\OutreachMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OutreachMessage $outreach) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->outreach->subject ?: $this->outreach->business->name.' için web sitenizi hazırladık');
    }

    public function headers(): Headers
    {
        $base = rtrim(config('app.url'), '/');

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$base.'/u/'.$this->outreach->token.'>',
        ]);
    }

    public function content(): Content
    {
        $base = rtrim(config('app.url'), '/');

        return new Content(
            view: 'mail.outreach',
            text: 'mail.outreach-text',
            with: [
                'body' => $this->outreach->body,
                'pixelUrl' => $base.'/t/o/'.$this->outreach->token.'.gif',
                'siteUrl' => $base.'/t/c/'.$this->outreach->token,
                'optOutUrl' => $base.'/u/'.$this->outreach->token,
                'businessName' => $this->outreach->business->name,
            ],
        );
    }
}
