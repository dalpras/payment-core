<?php declare(strict_types=1);

namespace DalPraS\Payment\Tests;

use DalPraS\Payment\Enum\Currency;
use DalPraS\Payment\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_from_decimal_trims_and_parses_italian_separators(): void
    {
        $money = Money::fromDecimal(' 1.234,50 ', Currency::EUR, ',', '.');

        self::assertSame(123450, $money->minorAmount());
    }
}
