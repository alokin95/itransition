<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Import\ProductImporter;
use App\Domain\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests that boot the Symfony kernel and exercise the full import
 * pipeline against a real database (SQLite in the test environment).
 * The schema is created from entity metadata before each test and dropped after,
 * so the migration SQL (MySQL-specific) is intentionally bypassed here.
 */
final class ProductImportIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProductImporter $importer;
    private string $csvPath;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->importer = self::getContainer()->get(ProductImporter::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->csvPath = sys_get_temp_dir() . '/import_test_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->csvPath)) {
            unlink($this->csvPath);
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param list<list<string>> $rows */
    private function writeCsv(array $rows): void
    {
        $handle = fopen($this->csvPath, 'w');
        fputcsv($handle, ['Product Code', 'Product Name', 'Product Description', 'Stock', 'Cost in GBP', 'Discontinued'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);
    }

    private function findProduct(string $code): ?Product
    {
        $this->em->clear(); // ensure we read from DB, not identity map
        return $this->em->getRepository(Product::class)->findOneBy(['code' => $code]);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testProductIsPersistedToDatabase(): void
    {
        $this->writeCsv([['P0001', 'TV', '32" TV', '10', '399.99', '']]);

        $result = $this->importer->import($this->csvPath);

        self::assertSame(1, $result->getSuccessful());

        $product = $this->findProduct('P0001');
        self::assertNotNull($product);
        self::assertSame('TV', $product->name);
        self::assertSame(10, $product->stock);
        self::assertSame(39999, $product->price->amount);
        self::assertNull($product->discontinuedAt);
    }

    public function testDiscontinuedProductIsPersistedWithTimestamp(): void
    {
        $this->writeCsv([['P0002', 'VCR', 'Top notch VCR', '12', '39.33', 'yes']]);

        $result = $this->importer->import($this->csvPath);

        self::assertSame(1, $result->getSuccessful());

        $product = $this->findProduct('P0002');
        self::assertNotNull($product);
        self::assertNotNull($product->discontinuedAt);
    }

    // -------------------------------------------------------------------------
    // Business rules
    // -------------------------------------------------------------------------

    public function testLowValueLowStockProductIsNotPersisted(): void
    {
        $this->writeCsv([['P0003', 'Cheap Thing', 'desc', '5', '3.00', '']]);

        $result = $this->importer->import($this->csvPath);

        self::assertSame(0, $result->getSuccessful());
        self::assertCount(1, $result->getSkipped());
        self::assertNull($this->findProduct('P0003'));
    }

    public function testExpensiveProductIsNotPersisted(): void
    {
        $this->writeCsv([['P0004', 'Expensive', 'desc', '5', '1200.00', '']]);

        $result = $this->importer->import($this->csvPath);

        self::assertSame(0, $result->getSuccessful());
        self::assertCount(1, $result->getSkipped());
        self::assertNull($this->findProduct('P0004'));
    }

    // -------------------------------------------------------------------------
    // Upsert
    // -------------------------------------------------------------------------

    public function testRepeatedImportUpdatesExistingProduct(): void
    {
        $this->writeCsv([['P0001', 'TV', 'Old description', '5', '299.99', '']]);
        $this->importer->import($this->csvPath);

        $this->writeCsv([['P0001', 'TV Updated', 'New description', '15', '399.99', '']]);
        $result = $this->importer->import($this->csvPath);

        self::assertSame(1, $result->getSuccessful());

        $product = $this->findProduct('P0001');
        self::assertSame('TV Updated', $product->name);
        self::assertSame(15, $product->stock);
        self::assertSame(39999, $product->price->amount);
    }

    public function testUpsertDoesNotCreateDuplicate(): void
    {
        $this->writeCsv([['P0001', 'TV', 'desc', '10', '399.99', '']]);
        $this->importer->import($this->csvPath);
        $this->importer->import($this->csvPath);

        $products = $this->em->getRepository(Product::class)->findBy(['code' => 'P0001']);
        self::assertCount(1, $products);
    }

    // -------------------------------------------------------------------------
    // Test mode
    // -------------------------------------------------------------------------

    public function testTestModeDoesNotPersistToDatabase(): void
    {
        $this->writeCsv([['P0001', 'TV', 'desc', '10', '399.99', '']]);

        $result = $this->importer->import($this->csvPath, testMode: true);

        self::assertSame(1, $result->getSuccessful());
        self::assertNull($this->findProduct('P0001'));
    }

    // -------------------------------------------------------------------------
    // Invalid CSV structure
    // -------------------------------------------------------------------------

    public function testMissingRequiredCsvColumnsThrowsException(): void
    {
        $handle = fopen($this->csvPath, 'w');
        fputcsv($handle, ['Name', 'Price'], ',', '"', '');
        fputcsv($handle, ['TV', '399.99'], ',', '"', '');
        fclose($handle);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing required columns/i');

        $this->importer->import($this->csvPath);
    }

    // -------------------------------------------------------------------------
    // Full stock.csv smoke test
    // -------------------------------------------------------------------------

    public function testRealStockCsvProducesExpectedCounts(): void
    {
        $result = $this->importer->import(__DIR__ . '/../../storage/stock.csv');

        // P0011 (malformed), P0012 (duplicate name — different code, valid),
        // P0015 (duplicate code) → 1 failure; P0015 ($4.33 prefix) → imported
        // P0027, P0028 (>£1000), various <£5 AND <10 stock → skipped
        self::assertGreaterThan(0, $result->getSuccessful());
        self::assertGreaterThan(0, count($result->getSkipped()));
        self::assertGreaterThan(0, count($result->getFailures()));

        // Total processed = all rows in the file
        self::assertSame(
            $result->getSuccessful() + count($result->getSkipped()) + count($result->getFailures()),
            $result->getProcessed(),
        );
    }
}
