<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_normalizes_common_formats(string $input): void
    {
        $this->assertSame('09171234567', PhoneNumber::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            'local' => ['09171234567'],
            'spaced' => ['0917 123 4567'],
            'dashed' => ['0917-123-4567'],
            'plus 63' => ['+639171234567'],
            'plus 63 spaced' => ['+63 917 123 4567'],
            '63 prefix' => ['639171234567'],
            'no leading zero' => ['917 123 4567'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_numbers(?string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
        $this->assertFalse(PhoneNumber::isValid($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'too short' => ['0917123'],
            'landline' => ['028123456'],
            'letters' => ['not a number'],
            'wrong prefix' => ['08171234567'],
        ];
    }

    public function test_it_formats_for_display_and_international_use(): void
    {
        $this->assertSame('0917 123 4567', PhoneNumber::format('+639171234567'));
        $this->assertSame('+639171234567', PhoneNumber::international('0917 123 4567'));
        $this->assertNull(PhoneNumber::international('nope'));
    }
}
