<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentStatus;

/**
 * Normalized response returned by a provider after checkout creation.
 *
 * The generic providerPaymentId/providerToken fields are intentionally kept small
 * for application-level display and indexing. Provider-specific identifiers that
 * will be needed later (Nexi operationId, PayPal capture/authorization ids, etc.)
 * should be returned through $metadata so PaymentManager can persist and reuse
 * them automatically for complete/capture/refund/cancel/sync operations.
 */
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
        /**
         * Provider-specific, normalized metadata to merge into the stored Payment.
         * Examples: nexi_order_id, nexi_security_token, paypal_order_id.
         */
        public readonly array $metadata = [],
    ) {
    }
}
