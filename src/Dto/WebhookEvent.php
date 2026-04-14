<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

final class WebhookEvent
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $eventType,
        public readonly ?string $providerPaymentId,
        public readonly array $payload,
        public readonly array $headers = [],
    ) {
    }
}
