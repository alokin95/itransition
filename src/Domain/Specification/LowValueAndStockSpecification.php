<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;

final class LowValueAndStockSpecification implements SkipSpecification
{
    private readonly Money $threshold;

    public function __construct()
    {
        $this->threshold = Money::fromString('5.00');
    }

    public function isSatisfiedBy(Product $product): bool
    {
        return $product->price->isLessThan($this->threshold) && $product->stock < 10;
    }

    public function reason(): string
    {
        return 'Cost is less than £5 and stock is less than 10';
    }
}
