<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Domain\Import\ParsedRowDto;
use App\Domain\Import\ProductReader;
use InvalidArgumentException;
use League\Csv\CharsetConverter;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;

final class LeagueCsvReader implements ProductReader
{
    private const REQUIRED_COLUMNS = ['Product Code', 'Product Name', 'Product Description', 'Cost in GBP'];

    /**
     * @return iterable<ParsedRowDto>
     *
     * @throws UnavailableStream
     * @throws Exception
     * @throws InvalidArgumentException when required columns are missing
     */
    public function read(string $path): iterable
    {
        $csv = Reader::from($path, 'r');
        $csv->setHeaderOffset(0);
        // PHP 8.4 deprecated the default backslash escape character. RFC 4180
        // does not define an escape character so an empty string is correct here.
        $csv->setEscape('');

        // Supplier files may use the legacy Latin 1 encoding. Converting to UTF8
        // before processing prevents character corruption in utf8mb4 columns.
        CharsetConverter::addTo($csv, 'ISO-8859-1', 'UTF-8');

        $this->assertRequiredColumnsPresent($csv->getHeader());

        foreach ($csv->getRecords() as $record) {
            yield new ParsedRowDto(
                code: trim($record['Product Code'] ?? ''),
                name: trim($record['Product Name'] ?? ''),
                description: trim($record['Product Description'] ?? ''),
                stock: $record['Stock'] ?? null,
                price: $record['Cost in GBP'] ?? null,
                discontinued: $record['Discontinued'] ?? null,
            );
        }
    }

    /** @param string[] $header */
    private function assertRequiredColumnsPresent(array $header): void
    {
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'CSV file is missing required columns: %s',
                implode(', ', $missing),
            ));
        }
    }
}
