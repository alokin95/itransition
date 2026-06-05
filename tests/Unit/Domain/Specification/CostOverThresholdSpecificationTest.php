<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Specification;

use App\Domain\Specification\CostOverThresholdSpecification;
use App\Domain\Entity\Product;
use PHPUnit\Framework\TestCase;

final class CostOverThresholdSpecificationTest extends TestCase
{
    private CostOverThresholdSpecification $spec;

    protected function setUp(): void
    {
        $this->spec = new CostOverThresholdSpecification();
    }

    public function testSkipsProductCostingMoreThan1000(): void
    {
        $product = Product::fromPrimitives('P001', 'TV', 'desc', 5, '1000.01');

        self::assertTrue($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipProductCostingExactly1000(): void
    {
        $product = Product::fromPrimitives('P002', 'TV', 'desc', 5, '1000.00');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testDoesNotSkipProductCostingLessThan1000(): void
    {
        $product = Product::fromPrimitives('P003', 'TV', 'desc', 5, '999.99');

        self::assertFalse($this->spec->isSatisfiedBy($product));
    }

    public function testReasonMentionsCurrency(): void
    {
        self::assertStringContainsString('£', $this->spec->reason());
        self::assertStringContainsString('1000', $this->spec->reason());
    }
}
