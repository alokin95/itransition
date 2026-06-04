<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;

final class CostOverThresholdSpecification implements SkipSpecification
{
    private readonly Money $threshold;

    public function __construct()
    {
        $this->threshold = Money::fromString('1000.00');
    }

    public function isSatisfiedBy(Product $product): bool
    {
        return $product->price->isGreaterThan($this->threshold);
    }

    public function reason(): string
    {
        return 'Cost exceeds £1000';
    }
}
