<?php declare(strict_types=1);

namespace DalPraS\Payment\ValueObject;

final class LineItem
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $quantity,
        public readonly Money $unitPrice,
        public readonly ?Money $taxAmount = null,
        public readonly ?Money $discountAmount = null,
        public readonly ?string $description = null,
        public readonly array $metadata = [],
    ) {
    }

    public function total(): Money
    {
        $total = $this->unitPrice->multiply($this->quantity);
        if ($this->taxAmount !== null) {
            $total = $total->plus($this->taxAmount);
        }
        if ($this->discountAmount !== null) {
            $total = $total->minus($this->discountAmount);
        }
        return $total;
    }

    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice->toArray(),
            'taxAmount' => $this->taxAmount?->toArray(),
            'discountAmount' => $this->discountAmount?->toArray(),
            'description' => $this->description,
            'metadata' => $this->metadata,
            'total' => $this->total()->toArray(),
        ];
    }
}
