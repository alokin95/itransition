<?php

declare(strict_types=1);

namespace App\Domain\Import;

readonly class ParsedRowDto
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public ?string $stock,
        public ?string $price,
        public ?string $discontinued,
    ) {
    }
}
