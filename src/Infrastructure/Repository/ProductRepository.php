<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Product;
use App\Domain\Repository\Flusher;
use App\Domain\Repository\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class ProductRepository implements ProductRepositoryInterface, Flusher
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
    }

    public function findByCode(string $code): ?Product
    {
        return $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $code]);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
