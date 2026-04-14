<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentStatus;

class OperationResult
{
    /** @param list<string> $transactionIds */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $providerPaymentId = null,
        public readonly array $transactionIds = [],
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }
}
