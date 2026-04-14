<?php declare(strict_types=1);

namespace DalPraS\Payment\ValueObject;

use DalPraS\Payment\Enum\Currency;
use InvalidArgumentException;

final class Money
{
    public function __construct(
        private readonly int $minorAmount,
        private readonly Currency $currency,
    ) {
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromDecimal(string $amount, Currency $currency): self
    {
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a decimal string with max 2 decimals.');
        }

        $normalized = number_format((float) $amount, 2, '.', '');
        return new self((int) round(((float) $normalized) * 100), $currency);
    }

    public function minorAmount(): int
    {
        return $this->minorAmount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->minorAmount * $multiplier, $this->currency);
    }

    public function decimal(): string
    {
        return number_format($this->minorAmount / 100, 2, '.', '');
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorAmount === $other->minorAmount;
    }

    public function toArray(): array
    {
        return [
            'minorAmount' => $this->minorAmount,
            'decimal' => $this->decimal(),
            'currency' => $this->currency->value,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
