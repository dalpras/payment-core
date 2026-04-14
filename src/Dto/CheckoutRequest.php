<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\Customer;
use DalPraS\Payment\ValueObject\LineItem;

final class CheckoutRequest
{
    /** @param list<LineItem> $items */
    public function __construct(
        public readonly string $providerCode,
        public readonly string $paymentReference,
        public readonly string $merchantReference,
        public readonly Customer $customer,
        public readonly array $items,
        public readonly AmountBreakdown $amounts,
        public readonly string $returnUrl,
        public readonly string $cancelUrl,
        public readonly ?string $webhookUrl = null,
        public readonly PaymentIntent $intent = PaymentIntent::SALE,
        public readonly ?string $locale = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $correlationId = null,
        public readonly array $metadata = [],
        public readonly array $providerOptions = [],
    ) {
    }
}
