<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Amounts are stored as integer minor units to avoid floating point rounding
 * errors when multiplying or comparing prices. For example 9.99 GBP is stored
 * as 999 so all arithmetic stays in integer space.
 */
readonly class Money
{
    private function __construct(
        public int $amount,
        public string $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a valid ISO 4217 code.');
        }
    }

    /**
     * Negative values indicate malformed or tampered supplier data and are
     * rejected before non numeric characters are stripped. Stripping first
     * would silently turn a negative into a positive.
     */
    /**
     * Negative values indicate malformed or tampered supplier data and are
     * rejected before non numeric characters are stripped. Stripping first
     * would silently turn a negative into a positive.
     */
    public static function fromString(string $value, string $currency = 'GBP'): self
    {
        $trimmed = trim($value);

        if (str_starts_with($trimmed, '-')) {
            throw new InvalidArgumentException("Cannot parse monetary value: \"$value\".");
        }

        $cleaned = preg_replace('/[^\d.]/', '', $trimmed);

        if ($cleaned === '' || !is_numeric($cleaned)) {
            throw new InvalidArgumentException("Cannot parse monetary value: \"$value\".");
        }

        return new self((int) round((float) $cleaned * 100), $currency);
    }

    public static function fromMinorUnits(int $pence, string $currency = 'GBP'): self
    {
        return new self($pence, $currency);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
