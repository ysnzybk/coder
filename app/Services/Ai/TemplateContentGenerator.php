<?php

namespace App\Services\Ai;

use App\Models\Business;

/**
 * API anahtarı olmadan (veya Claude hata/ret verdiğinde) kullanılan, şablon tabanlı içerik.
 * Uydurma bilgi içermez; yalnızca firma adı, ilçe ve sektörden türetilir.
 */
class TemplateContentGenerator implements SiteContentGenerator
{
    private const PRESETS = [
        'petshop' => [
            'palette' => 'orman', 'font' => 'samimi',
            'services' => [
                ['Mama & Ödül', 'Kedi, köpek, kuş ve balıklar için kaliteli mama ve ödül çeşitleri.'],
                ['Aksesuar', 'Tasma, yatak, oyuncak ve taşıma çantalarında geniş seçenek.'],
                ['Bakım Ürünleri', 'Şampuan, tarak ve hijyen ürünleriyle dostunuzun bakımı.'],
                ['Akvaryum & Kafes', 'Akvaryum, kafes ve ekipmanlarında doğru ürün tavsiyesi.'],
            ],
            'image' => ['cozy pet shop interior with shelves of pet food and toys, warm light, photorealistic', 'happy golden retriever and cat sitting together, soft studio light, photorealistic', 'colorful pet accessories collars and toys arranged on wooden table, photorealistic'],
        ],
        'restoran' => [
            'palette' => 'gunbatimi', 'font' => 'klasik',
            'services' => [
                ['Kahvaltı', 'Güne lezzetli ve doyurucu bir başlangıç.'],
                ['Ana Yemekler', 'Günlük taze malzemelerle hazırlanan yemekler.'],
                ['Tatlılar', 'Yemeğin üstüne keyifli tatlı seçenekleri.'],
                ['İçecekler', 'Sıcak ve soğuk içecek çeşitleri.'],
            ],
            'image' => ['inviting turkish restaurant interior with wooden tables, warm evening light, photorealistic', 'beautifully plated turkish dishes on a table, top view, natural light, photorealistic', 'fresh ingredients herbs and vegetables on rustic kitchen counter, photorealistic'],
        ],
        'guzellik' => [
            'palette' => 'gul', 'font' => 'modern',
            'services' => [
                ['Saç Kesim & Şekillendirme', 'Yüz hatlarınıza ve tarzınıza uygun kesim ve fön.'],
                ['Renklendirme', 'Boya, röfle ve bakım uygulamaları.'],
                ['Bakım', 'Saç ve cilt bakım uygulamaları.'],
                ['Özel Gün', 'Düğün, nişan ve özel günler için hazırlık.'],
            ],
            'image' => ['modern elegant hair salon interior with mirrors and chairs, bright soft light, photorealistic', 'hairdresser styling hair in a salon, close-up of hands and tools, photorealistic', 'beauty care products and brushes arranged neatly, pastel background, photorealistic'],
        ],
        'oto' => [
            'palette' => 'grafit', 'font' => 'guclu',
            'services' => [
                ['Periyodik Bakım', 'Yağ, filtre ve genel kontroller.'],
                ['Arıza Tespit', 'Elektronik ve mekanik arızaların tespiti.'],
                ['Lastik & Balans', 'Lastik değişimi, rot ve balans.'],
                ['Fren & Süspansiyon', 'Güvenli sürüş için fren ve süspansiyon kontrolleri.'],
            ],
            'image' => ['clean modern auto repair workshop with car on lift, bright light, photorealistic', 'mechanic hands working on car engine with tools, photorealistic', 'row of new car tires in a tire shop, photorealistic'],
        ],
        'genel' => [
            'palette' => 'okyanus', 'font' => 'modern',
            'services' => [
                ['Hizmetlerimiz', 'İhtiyacınıza uygun çözümler için bizimle iletişime geçin.'],
                ['Danışmanlık', 'Doğru seçimi yapmanız için yanınızdayız.'],
                ['Hızlı Destek', 'Sorularınıza hızlı dönüş.'],
            ],
            'image' => ['welcoming small local business storefront in a turkish street, daylight, photorealistic', 'friendly shop interior with organized shelves, warm light, photorealistic', 'happy customer being served at a local shop counter, photorealistic'],
        ],
    ];

    public function generate(Business $business): array
    {
        $template = $business->category?->template ?? 'genel';
        $p = self::PRESETS[$template] ?? self::PRESETS['genel'];
        $place = trim(($business->district?->name ?? '').', '.($business->province?->name ?? ''), ', ');
        $category = $business->category?->name ?? 'İşletme';

        return [
            'hero_title' => $business->name,
            'hero_subtitle' => "{$place} bölgesinde güvenilir {$category}",
            'tagline' => "{$place} · {$category}",
            'about' => "{$business->name}, {$place} bölgesinde müşterilerine hizmet veren bir {$category} işletmesidir. "
                .'Bizi ziyaret edebilir veya telefonla ulaşarak detaylı bilgi alabilirsiniz.',
            'highlights' => [
                ['icon' => '📍', 'title' => 'Kolay Ulaşım', 'text' => $business->address ?? $place],
                ['icon' => '⭐', 'title' => 'Müşteri Memnuniyeti', 'text' => $business->rating ? "Google'da {$business->rating} puan" : 'Memnuniyet önceliğimiz'],
                ['icon' => '🤝', 'title' => 'Samimi Hizmet', 'text' => 'Her müşterimize özenle yaklaşıyoruz.'],
            ],
            'services' => array_map(fn ($s) => ['name' => $s[0], 'description' => $s[1]], $p['services']),
            'review_summary' => $business->review_count > 0
                ? "Google'da {$business->review_count} değerlendirme."
                : '',
            'faq' => [
                ['q' => 'Çalışma saatleriniz nedir?', 'a' => 'Güncel çalışma saatlerimizi sayfadaki bölümden veya telefonla öğrenebilirsiniz.'],
                ['q' => 'Size nasıl ulaşabilirim?', 'a' => 'Sayfadaki arama veya WhatsApp butonlarıyla bize hemen ulaşabilirsiniz.'],
            ],
            'cta_text' => 'Hemen İletişime Geçin',
            'palette' => $p['palette'],
            'font_style' => $p['font'],
            'seo_title' => "{$business->name} | {$category} - {$place}",
            'seo_description' => "{$business->name}: {$place} bölgesinde {$category}. Adres, telefon ve çalışma saatleri.",
            'image_prompts' => [
                'hero' => $p['image'][0],
                'gallery_1' => $p['image'][1],
                'gallery_2' => $p['image'][2],
            ],
            '_source' => 'template',
        ];
    }
}
