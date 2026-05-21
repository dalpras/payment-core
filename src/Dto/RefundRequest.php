<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

/**
 * Request for a provider refund operation.
 *
 * Callers may pass providerPaymentId/metadata explicitly, but PaymentManager also
 * merges stored Payment metadata and the latest operation metadata before invoking
 * the provider. This lets provider packages resolve IDs such as Nexi operation_id
 * or PayPal capture_id without leaking provider details into application code.
 */
final class RefundRequest
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $paymentReference,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $idempotencyKey = null,
        /** Operation-specific metadata. Explicit request values override stored metadata. */
        public readonly array $metadata = [],
    ) {
    }
}
