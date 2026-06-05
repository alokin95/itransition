<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Import;

use App\Application\Import\ProductImporter;
use App\Domain\Entity\Product;
use App\Domain\Import\ParsedRowDto;
use App\Domain\Import\ProductReader;
use App\Domain\Repository\Flusher;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Specification\SkipSpecification;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

final class ProductImporterTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-15 12:00:00');
    }

    private function makeImporter(
        array $rows,
        ProductRepositoryInterface|null $repository = null,
        Flusher|null $flusher = null,
        ClockInterface|null $clock = null,
        SkipSpecification ...$specs,
    ): ProductImporter {
        $reader = $this->createStub(ProductReader::class);
        $reader->method('read')->willReturn($rows);

        return new ProductImporter(
            $reader,
            $repository ?? $this->createStub(ProductRepositoryInterface::class),
            $flusher ?? $this->createStub(Flusher::class),
            $clock ?? $this->clock,
            $specs,
        );
    }

    private function row(
        string $code = 'P001',
        string $name = 'TV',
        string $description = 'Great TV',
        ?string $stock = '10',
        ?string $price = '399.99',
        ?string $discontinued = null,
    ): ParsedRowDto {
        return new ParsedRowDto($code, $name, $description, $stock, $price, $discontinued);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testSuccessfulImportPersistsAndFlushes(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        $flusher = $this->createMock(Flusher::class);
        $flusher->expects(self::once())->method('flush');

        $result = $this->makeImporter([$this->row()], $repository, $flusher)->import('file.csv');

        self::assertSame(1, $result->getProcessed());
        self::assertSame(1, $result->getSuccessful());
        self::assertCount(0, $result->getSkipped());
        self::assertCount(0, $result->getFailures());
    }

    public function testMultipleValidRowsAreAllImported(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::exactly(3))->method('save');

        $result = $this->makeImporter([
            $this->row(code: 'P001'),
            $this->row(code: 'P002'),
            $this->row(code: 'P003'),
        ], $repository)->import('file.csv');

        self::assertSame(3, $result->getSuccessful());
    }

    // -------------------------------------------------------------------------
    // Test mode
    // -------------------------------------------------------------------------

    public function testTestModeDoesNotPersistOrFlush(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $flusher = $this->createMock(Flusher::class);
        $flusher->expects(self::never())->method('flush');

        $result = $this->makeImporter([$this->row()], $repository, $flusher)->import('file.csv', testMode: true);

        self::assertSame(1, $result->getSuccessful());
    }

    // -------------------------------------------------------------------------
    // Discontinued (Clock)
    // -------------------------------------------------------------------------

    public function testDiscontinuedProductSetsDiscontinuedAtFromClock(): void
    {
        $fixedNow = new DateTimeImmutable('2026-01-15 12:00:00');
        $clock = new MockClock($fixedNow);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->discontinuedAt == $fixedNow),
        );

        $this->makeImporter([$this->row(discontinued: 'yes')], $repository, clock: $clock)->import('file.csv');
    }

    public function testDiscontinuedProductIsStillImported(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        $result = $this->makeImporter([$this->row(discontinued: 'yes')], $repository)->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
    }

    public function testNonDiscontinuedProductHasNullDiscontinuedAt(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->discontinuedAt === null),
        );

        $this->makeImporter([$this->row(discontinued: null)], $repository)->import('file.csv');
    }

    public function testDiscontinuedFlagIsCaseInsensitive(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::exactly(3))->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->discontinuedAt !== null),
        );

        $this->makeImporter([
            $this->row(code: 'P001', discontinued: 'yes'),
            $this->row(code: 'P002', discontinued: 'YES'),
            $this->row(code: 'P003', discontinued: 'Yes'),
        ], $repository)->import('file.csv');
    }

    public function testClockIsOnlyCalledForDiscontinuedRows(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willReturn(new DateTimeImmutable());

        $this->makeImporter([
            $this->row(code: 'P001', discontinued: null),
            $this->row(code: 'P002', discontinued: 'yes'),
        ], clock: $clock)->import('file.csv');
    }

    // -------------------------------------------------------------------------
    // Validation failures
    // -------------------------------------------------------------------------

    public function testRowFailsWhenCodeIsMissing(): void
    {
        $result = $this->makeImporter([$this->row(code: '')])->import('file.csv');

        self::assertSame(0, $result->getSuccessful());
        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('missing product code', $result->getFailures()[0]);
    }

    public function testRowFailsWhenNameIsMissing(): void
    {
        $result = $this->makeImporter([$this->row(name: '')])->import('file.csv');

        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('missing product name', $result->getFailures()[0]);
    }

    public function testRowFailsWhenPriceIsMissing(): void
    {
        $result = $this->makeImporter([$this->row(price: null)])->import('file.csv');

        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('missing price', $result->getFailures()[0]);
    }

    public function testRowFailsWhenPriceIsEmptyString(): void
    {
        $result = $this->makeImporter([$this->row(price: '   ')])->import('file.csv');

        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('missing price', $result->getFailures()[0]);
    }

    public function testRowFailsWhenPriceIsInvalid(): void
    {
        $result = $this->makeImporter([$this->row(price: 'not-a-price')])->import('file.csv');

        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('invalid price format', $result->getFailures()[0]);
    }

    // -------------------------------------------------------------------------
    // Additional considerations — data quality
    // -------------------------------------------------------------------------

    public function testPriceWithDollarSymbolIsParsed(): void
    {
        // CSV may contain "$4.33" instead of "4.33" (as in stock.csv P0015)
        $result = $this->makeImporter([$this->row(price: '$4.33', stock: '32')])->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
    }

    public function testPriceWithPoundSymbolIsParsed(): void
    {
        $result = $this->makeImporter([$this->row(price: '£10.00')])->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
    }

    public function testNegativePriceIsRejected(): void
    {
        // Malformed/tampered data: negative price should not be silently imported
        $result = $this->makeImporter([$this->row(price: '-5.00')])->import('file.csv');

        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('invalid price format', $result->getFailures()[0]);
    }

    public function testMissingStockDefaultsToZero(): void
    {
        // Stock column may be absent (P0007 in stock.csv has empty stock)
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->stock === 0),
        );

        $result = $this->makeImporter([$this->row(stock: null, price: '50.00')], $repository)->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
    }

    public function testRowWithMissingColumnsIsRecordedAsFailure(): void
    {
        // Simulates P0011 from stock.csv: "error in export" — no price column at all
        $result = $this->makeImporter([$this->row(price: null, stock: null)])->import('file.csv');

        self::assertCount(1, $result->getFailures());
    }

    public function testProductCodeIsNormalisedToUpperCase(): void
    {
        // Codes from supplier may arrive in mixed case; we normalise to uppercase
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->code === 'P001'),
        );

        $this->makeImporter([$this->row(code: 'p001')], $repository)->import('file.csv');
    }

    public function testProductNameIsTrimmed(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with(
            self::callback(static fn(Product $p): bool => $p->name === 'TV'),
        );

        $this->makeImporter([$this->row(name: '  TV  ')], $repository)->import('file.csv');
    }

    // -------------------------------------------------------------------------
    // Duplicate detection
    // -------------------------------------------------------------------------

    public function testDuplicateCodeInSameFileIsRecordedAsFailure(): void
    {
        $result = $this->makeImporter([
            $this->row(code: 'P001'),
            $this->row(code: 'P001'),
        ])->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('duplicate', $result->getFailures()[0]);
    }

    public function testDuplicateOfSkippedRowIsStillDetected(): void
    {
        // First row is skipped by spec. Second row has the same code — must be a duplicate, not skipped again.
        $spec = $this->createStub(SkipSpecification::class);
        $spec->method('isSatisfiedBy')->willReturnOnConsecutiveCalls(true, false);
        $spec->method('reason')->willReturn('too cheap');

        $result = $this->makeImporter([
            $this->row(code: 'P001'),
            $this->row(code: 'P001'),
        ], specs: $spec)->import('file.csv');

        self::assertCount(1, $result->getSkipped());
        self::assertCount(1, $result->getFailures());
        self::assertStringContainsString('duplicate', $result->getFailures()[0]);
    }

    // -------------------------------------------------------------------------
    // Skip specifications
    // -------------------------------------------------------------------------

    public function testSkipSpecificationIsApplied(): void
    {
        $spec = $this->createStub(SkipSpecification::class);
        $spec->method('isSatisfiedBy')->willReturn(true);
        $spec->method('reason')->willReturn('too cheap');

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $result = $this->makeImporter([$this->row()], $repository, specs: $spec)->import('file.csv');

        self::assertSame(0, $result->getSuccessful());
        self::assertCount(1, $result->getSkipped());
        self::assertStringContainsString('too cheap', $result->getSkipped()[0]);
    }

    // -------------------------------------------------------------------------
    // Upsert
    // -------------------------------------------------------------------------

    public function testExistingProductIsUpdatedNotDuplicated(): void
    {
        $existing = Product::fromPrimitives('P001', 'Old Name', 'Old desc', 5, '100.00');

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('findByCode')->willReturn($existing);
        // save() is still called (persist is idempotent for managed entities)
        $repository->expects(self::once())->method('save');

        $result = $this->makeImporter([$this->row(name: 'New Name', price: '200.00')], $repository)->import('file.csv');

        self::assertSame(1, $result->getSuccessful());
        self::assertSame('New Name', $existing->name);
        self::assertSame(20000, $existing->price->amount);
    }

    // -------------------------------------------------------------------------
    // Flush behaviour
    // -------------------------------------------------------------------------

    public function testNoFlushWhenNothingSucceeded(): void
    {
        $flusher = $this->createMock(Flusher::class);
        $flusher->expects(self::never())->method('flush');

        $this->makeImporter([$this->row(code: '')], flusher: $flusher)->import('file.csv');
    }

    public function testFlushIsCalledOnceRegardlessOfRowCount(): void
    {
        $flusher = $this->createMock(Flusher::class);
        $flusher->expects(self::once())->method('flush');

        $this->makeImporter([
            $this->row(code: 'P001'),
            $this->row(code: 'P002'),
            $this->row(code: 'P003'),
        ], flusher: $flusher)->import('file.csv');
    }

    // -------------------------------------------------------------------------
    // Progress callback
    // -------------------------------------------------------------------------

    public function testProgressCallbackIsInvokedPerRow(): void
    {
        $callCount = 0;

        $this->makeImporter([
            $this->row(code: 'P001'),
            $this->row(code: 'P002'),
            $this->row(code: 'P003'),
        ])->import('file.csv', onProgress: function () use (&$callCount): void {
            ++$callCount;
        });

        self::assertSame(3, $callCount);
    }

    public function testProgressCallbackIsCalledEvenForFailedRows(): void
    {
        $callCount = 0;

        $this->makeImporter([
            $this->row(code: ''),
            $this->row(code: 'P001'),
        ])->import('file.csv', onProgress: function () use (&$callCount): void {
            ++$callCount;
        });

        self::assertSame(2, $callCount);
    }
}
