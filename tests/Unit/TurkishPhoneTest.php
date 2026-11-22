<?php

namespace Tests\Unit;

use App\Services\Phone\TurkishPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TurkishPhoneTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['0532 123 45 67', '+905321234567', 'mobile'],
            ['+90 532 123 45 67', '+905321234567', 'mobile'],
            ['5321234567', '+905321234567', 'mobile'],
            ['0090 555 000 11 22', '+905550001122', 'mobile'],
            ['0216 345 67 89', '+902163456789', 'landline'],
            ['+90 212 000 00 00', '+902120000000', 'landline'],
            ['0850 123 45 67', '+908501234567', 'landline'],
            ['444 1 234', null, 'none'],
            [null, null, 'none'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize_and_type(?string $raw, ?string $normalized, string $type): void
    {
        $this->assertSame($normalized, TurkishPhone::normalize($raw));
        $this->assertSame($type, TurkishPhone::type($raw));
    }

    public function test_display_and_whatsapp_format(): void
    {
        $this->assertSame('0532 123 45 67', TurkishPhone::display('+905321234567'));
        $this->assertSame('905321234567', TurkishPhone::forWhatsApp('0532 123 45 67'));
    }
}
