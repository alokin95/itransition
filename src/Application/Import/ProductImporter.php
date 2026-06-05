<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Entity\Product;
use App\Domain\Import\ParsedRowDto;
use App\Domain\Import\ProductReader;
use App\Domain\Repository\Flusher;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Specification\SkipSpecification;
use App\Domain\ValueObject\Money;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

readonly class ProductImporter
{
    /**
     * @param iterable<SkipSpecification> $specifications injected via a DI tagged iterator;
     *                                                    each implementation encapsulates one skip rule
     */
    public function __construct(
        private ProductReader $reader,
        private ProductRepositoryInterface $repository,
        private Flusher $flusher,
        private ClockInterface $clock,
        private iterable $specifications,
    ) {
    }

    public function import(string $path, bool $testMode = false, \Closure|null $onProgress = null): Result
    {
        $result = new Result();
        $processedProductCodes = [];

        foreach ($this->reader->read($path) as $row) {
            if ($onProgress !== null) {
                $onProgress();
            }

            if ($error = $this->validate($row)) {
                $result->recordFailure($error);
                continue;
            }

            if (isset($processedProductCodes[$row->code])) {
                $result->recordFailure("$row->code: duplicate product code.");
                continue;
            }

            $product = $this->buildProduct($row, $result);
            if ($product === null) {
                continue;
            }

            // Registered before the skip check so a second occurrence of a code
            // whose first row was skipped is still detected as a duplicate.
            $processedProductCodes[$row->code] = true;

            if ($this->shouldBeSkipped($product, $row->code, $result)) {
                continue;
            }

            if (!$testMode) {
                $this->repository->save($product);
            }

            $result->recordSuccess();
        }

        // Single flush after all rows so the database round trip count is
        // proportional to successful imports rather than to total rows processed.
        if (!$testMode && $result->getSuccessful() > 0) {
            $this->flusher->flush();
        }

        return $result;
    }

    private function buildProduct(ParsedRowDto $row, Result $result): ?Product
    {
        try {
            $product = $this->findOrCreate($row);

            if (strtolower(trim($row->discontinued ?? '')) === 'yes') {
                $product->markAsDiscontinued($this->clock->now());
            }

            return $product;
        } catch (InvalidArgumentException $e) {
            $result->recordFailure("$row->code: {$e->getMessage()}");

            return null;
        }
    }

    private function findOrCreate(ParsedRowDto $row): Product
    {
        $product = $this->repository->findByCode($row->code);

        if ($product !== null) {
            $product->name        = $row->name;
            $product->description = $row->description;
            $product->stock       = (int) ($row->stock ?? 0);
            $product->price       = Money::fromString($row->price ?? '');

            return $product;
        }

        return Product::fromPrimitives(
            code: $row->code,
            name: $row->name,
            description: $row->description,
            stock: (int) ($row->stock ?? 0),
            rawPrice: $row->price ?? '',
        );
    }

    private function shouldBeSkipped(Product $product, string $code, Result $result): bool
    {
        foreach ($this->specifications as $specification) {
            if ($specification->isSatisfiedBy($product)) {
                $result->recordSkipped($code, $specification->reason());

                return true;
            }
        }

        return false;
    }

    private function validate(ParsedRowDto $row): ?string
    {
        if ($row->code === '') {
            return 'Row skipped: missing product code.';
        }

        if ($row->name === '') {
            return "$row->code: missing product name.";
        }

        if ($row->price === null || trim($row->price) === '') {
            return "$row->code: missing price.";
        }

        try {
            Money::fromString($row->price);
        } catch (InvalidArgumentException) {
            return "$row->code: invalid price format \"$row->price\".";
        }

        return null;
    }
}
