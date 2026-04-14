<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\Customer;
use DalPraS\Payment\ValueObject\LineItem;

final class PaymentDraft
{
    /** @param list<LineItem> $items */
    public function __construct(
        public readonly string $merchantReference,
        public readonly Customer $customer,
        public readonly array $items,
        public readonly AmountBreakdown $amounts,
        public readonly PaymentIntent $intent = PaymentIntent::SALE,
        public readonly array $metadata = [],
        public readonly array $allowedProviders = [],
    ) {
    }
}
