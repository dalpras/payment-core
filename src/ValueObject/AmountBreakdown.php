<?php declare(strict_types=1);

namespace DalPraS\Payment\ValueObject;

final class AmountBreakdown
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $taxTotal,
        public readonly Money $discountTotal,
        public readonly Money $shippingTotal,
        public readonly Money $grandTotal,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal->toArray(),
            'taxTotal' => $this->taxTotal->toArray(),
            'discountTotal' => $this->discountTotal->toArray(),
            'shippingTotal' => $this->shippingTotal->toArray(),
            'grandTotal' => $this->grandTotal->toArray(),
        ];
    }
}
