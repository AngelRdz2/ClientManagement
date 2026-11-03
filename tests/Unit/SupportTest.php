<?php

namespace Tests\Unit;

use App\Support\Dui;
use App\Support\Money;
use App\Support\Phone;
use Tests\TestCase;

class SupportTest extends TestCase
{
    public function test_dui_check_digit_validation(): void
    {
        $this->assertTrue(Dui::isValid('01234567-8'));
        $this->assertTrue(Dui::isValid('012345678'));
        $this->assertFalse(Dui::isValid('01234567-9'));
        $this->assertFalse(Dui::isValid('1234'));
        $this->assertSame('01234567-8', Dui::normalize('012345678'));
        $this->assertSame('01234567-8', Dui::fromBase('01234567'));
        $this->assertSame('*****567-8', Dui::mask('01234567-8'));
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('50370123456', Phone::normalize('7012-3456'));
        $this->assertSame('50370123456', Phone::normalize('+503 7012 3456'));
        $this->assertSame('14155238886', Phone::normalize('+1 (415) 523-8886'));
        $this->assertNull(Phone::normalize(''));
        $this->assertSame('+503 7012-3456', Phone::format('70123456'));
    }

    public function test_money_format(): void
    {
        $this->assertSame('$1,234.50', Money::format(1234.5));
        $this->assertSame('$12.5K', Money::compact(12500));
    }
}
