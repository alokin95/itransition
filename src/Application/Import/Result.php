<?php

declare(strict_types=1);

namespace App\Application\Import;

class Result
{
    private int $processed = 0;
    private int $successful = 0;
    /** @var string[] */
    private array $skipped = [];

    /** @var string[] */
    private array $failures = [];

    public function recordSuccess(): void
    {
        ++$this->processed;
        ++$this->successful;
    }

    public function recordSkipped(string $code, string $reason): void
    {
        ++$this->processed;
        $this->skipped[] = "$code: $reason";
    }

    public function recordFailure(string $reason): void
    {
        ++$this->processed;
        $this->failures[] = $reason;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getSuccessful(): int
    {
        return $this->successful;
    }

    /** @return string[] */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /** @return string[] */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
