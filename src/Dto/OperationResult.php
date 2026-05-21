<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentStatus;

/**
 * Base result for every provider operation after checkout creation.
 *
 * Providers should expose machine-readable identifiers in $metadata instead of
 * forcing applications to parse raw payloads. Core stores this metadata on both
 * Payment and PaymentOperation so future operations can be enriched automatically.
 */
class OperationResult
{
    /** @param list<string> $transactionIds */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $providerPaymentId = null,
        public readonly array $transactionIds = [],
        public readonly ?string $message = null,
        public readonly array $raw = [],
        /**
         * Normalized provider metadata discovered by the operation.
         * Examples: operation_id, capture_id, authorization_id.
         */
        public readonly array $metadata = [],
    ) {
    }
}
