<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Specification;

use App\Domain\Entity\Product;
use App\Domain\Specification\LowValueAndStockSpecification;
use PHPUnit\Framework\TestCase;

final class LowValueAndStockSpecificationTest extends TestCase
{
    private LowValueAndStockSpecification $spec;

    protected function setUp(): void
    {
        $this->spec = new LowValueAndStockSpecification();
    }

    public function testSkipsProductBelowPriceThresholdAndLowStock(): void
    {
        $product = Product::fromPrimitives('P001', 'Cable', 'desc', 9, '4.99');

        self::assertTrue($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipWhenPriceIsExactly5(): void
    {
        $product = Product::fromPrimitives('P002', 'Cable', 'desc', 9, '5.00');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipWhenStockIsExactly10(): void
    {
        $product = Product::fromPrimitives('P003', 'Cable', 'desc', 10, '4.99');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipWhenOnlyPriceIsBelowThreshold(): void
    {
        // Price < £5 but stock >= 10 — should import
        $product = Product::fromPrimitives('P004', 'Cable', 'desc', 15, '3.00');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipWhenOnlyStockIsBelowThreshold(): void
    {
        // Stock < 10 but price >= £5 — should import
        $product = Product::fromPrimitives('P005', 'Cable', 'desc', 5, '10.00');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testReasonMentionsCurrencyAndStock(): void
    {
        self::assertStringContainsString('£', $this->spec->reason());
        self::assertStringContainsString('5', $this->spec->reason());
        self::assertStringContainsString('10', $this->spec->reason());
    }
}
