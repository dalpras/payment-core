<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentStatus;

final class CheckoutResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly bool $redirectRequired,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $providerToken = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
        public readonly array $raw = [],
        public readonly ?string $message = null,
    ) {
    }
}
