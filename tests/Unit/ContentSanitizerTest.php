<?php

namespace Tests\Unit;

use App\Services\Ai\ContentSanitizer;
use PHPUnit\Framework\TestCase;

class ContentSanitizerTest extends TestCase
{
    public function test_strips_phones_links_prices_and_clamps_length(): void
    {
        $clean = (new ContentSanitizer)->clean([
            'about' => 'Bizi 0532 123 45 67 numarasından arayın, www.ornek.com veya info@ornek.com adresine yazın. Paketler 1.500 TL den başlar. '.str_repeat('Uzun. ', 300),
            'hero_title' => '<b>Pati Dostu</b>',
            'services' => [
                ['name' => 'Mama', 'description' => 'Kaliteli mama'],
                ['name' => '', 'description' => 'Adı yok, atılmalı'],
                'bozuk',
            ],
            'image_prompts' => ['hero' => 'cozy shelves close-up', 'gallery_1' => 'dog toys detail', 'gallery_2' => 'cat treats detail'],
        ]);

        $this->assertStringNotContainsString('0532', $clean['about']);
        $this->assertStringNotContainsString('ornek.com', $clean['about']);
        $this->assertStringNotContainsString('TL', $clean['about']);
        $this->assertLessThanOrEqual(700, mb_strlen($clean['about']));
        $this->assertSame('Pati Dostu', $clean['hero_title']);
        $this->assertCount(1, $clean['services']);
        $this->assertStringContainsString('no signs', $clean['image_prompts']['hero']);
    }

    public function test_risky_image_prompts_are_dropped_and_good_ones_kept(): void
    {
        $clean = (new ContentSanitizer)->clean(['image_prompts' => [
            'hero' => 'restaurant storefront with a neon sign at night',
            'gallery_1' => 'close-up of fresh bread and olives on a wooden table',
            'gallery_2' => '',
        ], 'services' => 'yanlış tür', 'cta_text' => 'Sipariş vermek veya rezervasyon yapmak için hemen tıklayın']);

        $this->assertSame(['gallery_1'], array_keys($clean['image_prompts']));
        $this->assertStringContainsString('no signs', $clean['image_prompts']['gallery_1']);
        $this->assertArrayNotHasKey('services', $clean);
        $this->assertArrayNotHasKey('cta_text', $clean, 'Uzun buton metni atılır, şablondaki varsayılan kullanılır');
    }
}
