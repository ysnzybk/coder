<?php

namespace App\Services\Outreach;

use App\Models\OutreachMessage;
use App\Services\Phone\TurkishPhone;

class WhatsAppLinkBuilder
{
    public function build(OutreachMessage $message): ?string
    {
        $number = TurkishPhone::forWhatsApp($message->recipient);

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($message->body) : null;
    }
}
