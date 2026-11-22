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
            'image_prompts' => ['hero' => 'a cozy shop', 'gallery_1' => 'dog', 'gallery_2' => 'cat'],
        ]);

        $this->assertStringNotContainsString('0532', $clean['about']);
        $this->assertStringNotContainsString('ornek.com', $clean['about']);
        $this->assertStringNotContainsString('TL', $clean['about']);
        $this->assertLessThanOrEqual(700, mb_strlen($clean['about']));
        $this->assertSame('Pati Dostu', $clean['hero_title']);
        $this->assertCount(1, $clean['services']);
        $this->assertStringContainsString('no text, no logo', $clean['image_prompts']['hero']);
    }

    public function test_incomplete_image_prompts_are_dropped_so_template_fills_them(): void
    {
        $clean = (new ContentSanitizer)->clean(['image_prompts' => ['hero' => 'x'], 'services' => 'yanlış tür']);

        $this->assertArrayNotHasKey('image_prompts', $clean);
        $this->assertArrayNotHasKey('services', $clean);
    }
}
