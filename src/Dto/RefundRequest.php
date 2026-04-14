<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

final class RefundRequest
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $paymentReference,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {
    }
}
