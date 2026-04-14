<?php declare(strict_types=1);

namespace DalPraS\Payment\ValueObject;

final class Customer
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $email = null,
        public readonly ?string $fullName = null,
        public readonly ?Address $billingAddress = null,
        public readonly ?Address $shippingAddress = null,
        public readonly array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'fullName' => $this->fullName,
            'billingAddress' => $this->billingAddress?->toArray(),
            'shippingAddress' => $this->shippingAddress?->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
