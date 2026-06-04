<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Entity\Product;

interface SkipSpecification
{
    public function isSatisfiedBy(Product $product): bool;

    public function reason(): string;
}
