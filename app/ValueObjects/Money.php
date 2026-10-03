<?php

namespace App\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Money implements \JsonSerializable
{
    private BigDecimal $value;

    public function __construct(string|int $value = '0')
    {
        $this->value = BigDecimal::of($value);
    }

    public function add(self $other): self
    {
        return new self((string) $this->value->plus($other->value));
    }

    public function subtract(self $other): self
    {
        return new self((string) $this->value->minus($other->value));
    }

    /** Exact multiplication by a whole count, e.g. a per-person allowance times the people counted. */
    public function multiply(string|int $factor): self
    {
        return new self((string) $this->value->multipliedBy($factor));
    }

    /**
     * This amount's proportional share of a reduced total: $this x $numerator / $denominator.
     *
     * Division is the one operation that cannot stay exact, so the result is truncated to four
     * decimal places and the caller is expected to give the remainder to the final share. Used
     * only for presentation — never to decide a taxable amount.
     */
    public function proportion(self $numerator, self $denominator): self
    {
        if ($denominator->value->isZero()) {
            return new self;
        }

        return new self((string) $this->value->multipliedBy($numerator->value)
            ->dividedBy($denominator->value, 4, RoundingMode::Down));
    }

    public function percentage(string $rate): self
    {
        return new self((string) $this->value->multipliedBy($rate)->dividedByExact(100));
    }

    /**
     * How many whole $unit amounts fit inside this one, with any remainder discarded.
     *
     * ใบแนบ ข้อ 20 grants "10,000 บาท ต่อทุกจำนวน 1,000,000 บาท". "ต่อทุกจำนวน" counts completed
     * units and the form prints no proportion for a part-finished one, so 2,900,000 baht of
     * spending completes two units, not 2.9. Truncation here is the rule, not a rounding choice.
     */
    public function wholeMultiplesOf(self $unit): int
    {
        if ($unit->value->isNegativeOrZero()) {
            throw new \InvalidArgumentException('A unit must be a positive amount.');
        }

        return $this->value->dividedBy($unit->value, 0, RoundingMode::Down)->toInt();
    }

    public function compare(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    public function min(self $other): self
    {
        return $this->compare($other) <= 0 ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->compare($other) >= 0 ? $this : $other;
    }

    public function absolute(): self
    {
        return new self((string) $this->value->abs());
    }

    public function __toString(): string
    {
        $value = $this->value->strippedOfTrailingZeros();

        return (string) $value->toScale(max(2, $value->getScale()));
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
