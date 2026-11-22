<?php

namespace App\Http\Controllers;

use App\Models\OutreachMessage;
use App\Services\Outreach\OutreachService;
use RuntimeException;

/** Admin: onaylı mesajı "gönderildi" işaretler ve WhatsApp'a yönlendirir. */
class WhatsAppController extends Controller
{
    public function __invoke(OutreachMessage $message, OutreachService $outreach)
    {
        try {
            return redirect()->away($outreach->openWhatsApp($message->load('business')));
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
