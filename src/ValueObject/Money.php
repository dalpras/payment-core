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
        $fractionDigits = $currency->fractionDigits();
        $pattern = $fractionDigits === 0
            ? '/^-?\d+$/'
            : sprintf('/^-?\d+(\.\d{1,%d})?$/', $fractionDigits);

        if (!preg_match($pattern, $amount)) {
            throw new InvalidArgumentException(sprintf(
                'Amount must be a decimal string with max %d decimals.',
                $fractionDigits
            ));
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        $factor = 10 ** $fractionDigits;

        [$major, $minor] = array_pad(explode('.', $amount, 2), 2, '0');
        $minor = $fractionDigits === 0 ? '0' : str_pad($minor, $fractionDigits, '0');

        $minorAmount = ((int) $major * $factor) + (int) $minor;

        if ($negative) {
            $minorAmount *= -1;
        }

        return new self($minorAmount, $currency);
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

    public function decimal(string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        $fractionDigits = $this->currency->fractionDigits();
        $factor = 10 ** $fractionDigits;

        return number_format($this->minorAmount / $factor, $fractionDigits, $decimalSeparator, $thousandsSeparator);
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
