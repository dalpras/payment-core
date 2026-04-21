<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentIntent;

final class CompletionRequest
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $paymentReference,
        public readonly array $queryParams = [],
        public readonly array $bodyParams = [],
        public readonly ?string $expectedProviderPaymentId = null,
        public readonly ?PaymentIntent $expectedIntent = null,
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
