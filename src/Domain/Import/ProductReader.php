<?php

declare(strict_types=1);

namespace App\Domain\Import;

interface ProductReader
{
    /** @return iterable<ParsedRowDto> */
    public function read(string $path): iterable;
}
