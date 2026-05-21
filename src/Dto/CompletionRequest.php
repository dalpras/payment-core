<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentIntent;

/**
 * Browser-return completion request.
 *
 * Browser redirects often provide only UX parameters. PaymentManager enriches this
 * DTO with the stored providerPaymentId and metadata before it reaches the provider,
 * so applications do not have to manually pass Nexi order ids or PayPal order ids
 * again during completion.
 */
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
        /** Provider and application metadata merged by PaymentManager. */
        public readonly array $metadata = [],
        /** Optional correlation id propagated to provider APIs where supported. */
        public readonly ?string $correlationId = null,
    ) {
    }
}
