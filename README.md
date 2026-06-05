# Product CSV Importer

Symfony 8.1 console application that imports products from a CSV file into MySQL.

## Quick Start

```bash
docker compose up -d --build
```

The container entrypoint runs database migrations automatically before the app becomes ready.

**Run import:**

```bash
# Import into the database
docker compose exec app php bin/console app:import-products-from-file

# Dry run — processes everything but does not write to the database
docker compose exec app php bin/console app:import-products-from-file --test

# Custom file path
docker compose exec app php bin/console app:import-products-from-file path/to/file.csv
```

**Tear down:**

```bash
docker compose down      # stop containers
docker compose down -v   # stop and delete the MySQL volume
```

## Running Tests

```bash
# All tests (64 total: 55 unit, 9 integration)
docker compose exec app php vendor/bin/phpunit

# Unit tests only
docker compose exec app php vendor/bin/phpunit tests/Unit/

# Integration tests only (requires the running database container)
docker compose exec app php vendor/bin/phpunit tests/Integration/
```

**Static analysis:**

```bash
php -d memory_limit=512M vendor/bin/phpstan analyse   # level 9, zero errors
```

## Design Decisions

### Ports and Adapters

The domain and application layers have no dependency on any framework or infrastructure library.
Interfaces (`ProductReader`, `ProductRepositoryInterface`, `Flusher`) are defined in the domain;
the concrete implementations (`LeagueCsvReader`, `ProductRepository`) live in the infrastructure layer.
Swapping the CSV library or the database requires changing only the adapter, not the importer logic.

### Domain entities protecting their own invariants

`Product` uses PHP 8.4 property hooks to normalise data on assignment (`name` and `description`
are trimmed, `code` is uppercased and trimmed). The `id`, `addedAt`, `discontinuedAt` and `updatedAt`
fields are `private(set)` so no external code can set them directly. `fromPrimitives()` is the only
public way to construct a product with a price; it goes through `Money::fromString()` validation, so
a `Product` with an invalid price cannot exist.

### Value Objects

`Money` is a `readonly` class that stores prices as integer minor units (pence) to avoid floating point
rounding errors when multiplying or comparing values. It validates the ISO 4217 currency code and rejects
negative amounts in the constructor, so a `Money` instance is always in a valid state and can never be
mutated after creation.

### Specification pattern

Each skip rule (`LowValueAndStockSpecification`, `CostOverThresholdSpecification`) is a separate class
implementing `SkipSpecification`. All tagged implementations are injected into `ProductImporter` via a
Symfony `!tagged_iterator`. Adding a new import rule requires only a new tagged class; no existing code
changes. This applies the Open Closed Principle to the import pipeline.

### `yield` in the CSV reader

`LeagueCsvReader::read()` is a generator that `yield`s one `ParsedRowDto` at a time instead of collecting
all rows into an array and returning it. This keeps memory usage constant regardless of file size: only
one row lives in memory at a time while `ProductImporter` processes it.

### league/csv

The `league/csv` library was chosen over native `fgetcsv` for three reasons: it exposes a clean
header-offset API that maps column names to values directly; it ships a `CharsetConverter` that handles
encoding conversion from legacy Latin 1 supplier files to UTF8 in one line; and `setEscape('')` correctly
disables the escape character that PHP 8.4 deprecated (RFC 4180 does not define one).

### ClockInterface and the `onPreUpdate` limitation

`ProductImporter` injects `ClockInterface` (PSR-20) and passes the current time explicitly to
`Product::markAsDiscontinued($at)`. This makes the discontinued timestamp deterministic in tests
via `MockClock`.

`Product::onPreUpdate()` is a Doctrine lifecycle callback and cannot receive dependencies via the
service container — Doctrine invokes it directly on the entity instance. It therefore uses
`new DateTimeImmutable()`. The clean solution would be a Doctrine event listener that receives
`ClockInterface` via constructor injection, but that adds complexity beyond the scope of this task.

### Fault-tolerant batch processing

`ProductImporter::import()` never throws on a bad row. Validation failures, parse errors and duplicate
codes are accumulated in a `Result` object so the importer processes every row in the file and returns
a complete summary. The console command prints all skipped and failed rows at the end.

### Single flush at the end

`Flusher::flush()` is called once after all rows are processed rather than after every successful row.
This reduces database round trips to a number proportional to the number of successful imports rather
than the total number of rows. In-memory duplicate tracking and an upsert strategy (`findByCode` before
`persist`) ensure the flush never hits a unique constraint violation.

### PHPStan level 9

The codebase passes PHPStan at its strictest level with zero suppressions. This enforces fully typed
return types, no implicit `mixed`, and strict null handling across the entire `src/` tree.

## AI-Assisted Development

I used **Claude** (Anthropic) throughout the assignment as a development accelerator, primarily for
code generation and architectural discussion. It helped me bootstrap boilerplate-heavy parts (Doctrine
mappings, DI wiring, PHPUnit setup) so I could focus on design decisions. I reviewed and often reworked
every generated output before committing. All architectural choices — repository/Flusher split,
Specification pattern, upsert strategy, single-flush transaction boundary — were decisions I made;
Claude implemented what I described.

**Prompts used:**

1. Populate a `Product` entity from this SQL schema using Doctrine `#[Attribute]` mappings and PHP 8.4 property hooks.

2. Create a hash map in `ProductImporter` to track already-processed codes so the single `flush()` at the end of the transaction does not fail on the unique constraint.

3. Tag `SkipSpecification` implementations as iterable in `services.yaml` and inject them into `ProductImporter`.

4. Create a `ProductRepositoryInterface` and a Doctrine implementation. Also create a `Flusher` interface implemented by the same class so the application layer controls when the transaction commits.

5. Refactor `ProductImporter::import` into smaller private methods instead of one large method.

6. Add two new fields to the `Product` entity with Doctrine mappings: `intStock` and `intPricePence`.

7. Commit changed files with a concise but comprehensive commit message.

8. Implement upsert in the import command so that running the import multiple times updates existing products instead of failing on the unique constraint.

9. Improve the console command output: add a progress bar during import and format the final summary as a readable table with counts and per-row failure and skip reasons.

8. Create a `ClockInterface` and a real implementation so the codebase is not tied to `new DateTimeImmutable()`. Update existing tests to use a `MockClock`.

10. Cover the following CSV data-quality cases with unit tests: correct formatting for CSV and database, potential encoding or line-termination issues, and manual file interference that may invalidate some entries.

11. Set up PHPStan at level 9 and fix all reported errors.
