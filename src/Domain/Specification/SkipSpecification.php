<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Entity\Product;

/**
 * Adding a new import rule requires only a new implementation tagged with
 * app.skip_specification in the service container. ProductImporter receives
 * all tagged implementations via a DI tagged iterator and applies them in
 * order without needing to know about any specific rule. This is the
 * Open Closed Principle applied to the import pipeline.
 */
interface SkipSpecification
{
    public function isSatisfiedBy(Product $product): bool;

    public function reason(): string;
}
