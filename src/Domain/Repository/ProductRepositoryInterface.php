<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findByCode(string $code): ?Product;
}
