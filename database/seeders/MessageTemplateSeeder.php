<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        MessageTemplate::firstOrCreate(['channel' => 'whatsapp', 'is_default' => true], [
            'name' => 'Varsayılan WhatsApp teklifi',
            'body' => "Merhaba, {firma_adi} için bir web sitesi hazırladık 👇\n{site_linki}\n\n"
                .'Beğenirseniz kurulum {kurulum_ucreti} TL, üstüne aylık {bakim_ucreti} TL bakım. '
                ."Beğenmezseniz hiçbir ödeme yok.\n\n{gonderen_adi}\n\n"
                .'Bu konuda mesaj almak istemiyorsanız: {ret_linki}',
        ]);

        MessageTemplate::firstOrCreate(['channel' => 'email', 'is_default' => true], [
            'name' => 'Varsayılan e-posta teklifi',
            'subject' => '{firma_adi} için web sitenizi hazırladık',
            'body' => "Merhaba,\n\n{ilce} bölgesindeki işletmeniz {firma_adi} için bir web sitesi hazırladık. "
                ."Aşağıdaki butondan inceleyebilirsiniz.\n\n"
                .'Beğenirseniz kurulum ücreti {kurulum_ucreti} TL, üstüne aylık {bakim_ucreti} TL bakım. '
                ."Beğenmezseniz hiçbir ödeme yok.\n\nSaygılarımızla,\n{gonderen_adi}\n{gonderen_telefon}",
        ]);
    }
}
