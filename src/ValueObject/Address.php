<?php declare(strict_types=1);

namespace DalPraS\Payment\ValueObject;

final class Address
{
    public function __construct(
        public readonly ?string $fullName = null,
        public readonly ?string $line1 = null,
        public readonly ?string $line2 = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $province = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $phone = null,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
