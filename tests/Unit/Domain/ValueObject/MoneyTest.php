<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('validStringProvider')]
    public function testFromStringParsesCorrectly(string $input, int $expectedPence): void
    {
        $money = Money::fromString($input);

        self::assertSame($expectedPence, $money->amount);
        self::assertSame('GBP', $money->currency);
    }

    /** @return array<string, array{string, int}> */
    public static function validStringProvider(): array
    {
        return [
            'integer string'      => ['10', 1000],
            'decimal string'      => ['5.00', 500],
            'with pound symbol'   => ['£5.00', 500],
            'with dollar symbol'  => ['$4.33', 433],
            'rounding half up'    => ['0.999', 100],
            'zero'                => ['0', 0],
            'large value'         => ['1000.00', 100000],
            'single digit pence'  => ['0.01', 1],
        ];
    }

    public function testFromStringThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromString('');
    }

    public function testFromStringThrowsOnNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromString('abc');
    }

    public function testFromStringThrowsOnNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromString('-5.00');
    }

    public function testFromMinorUnits(): void
    {
        $money = Money::fromMinorUnits(1234);

        self::assertSame(1234, $money->amount);
        self::assertSame('GBP', $money->currency);
    }

    public function testIsGreaterThan(): void
    {
        $higher = Money::fromString('10.00');
        $lower  = Money::fromString('5.00');

        self::assertTrue($higher->isGreaterThan($lower));
        self::assertFalse($lower->isGreaterThan($higher));
        self::assertFalse($higher->isGreaterThan($higher));
    }

    public function testIsLessThan(): void
    {
        $higher = Money::fromString('10.00');
        $lower  = Money::fromString('5.00');

        self::assertTrue($lower->isLessThan($higher));
        self::assertFalse($higher->isLessThan($lower));
        self::assertFalse($lower->isLessThan($lower));
    }

    public function testEquals(): void
    {
        $a = Money::fromString('5.00');
        $b = Money::fromString('5.00');
        $c = Money::fromString('6.00');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testCustomCurrency(): void
    {
        $money = Money::fromString('10.00', 'USD');

        self::assertSame('USD', $money->currency);
    }

    public function testInvalidCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(100, 'gb');
    }
}
