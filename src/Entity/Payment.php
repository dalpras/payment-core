<?php declare(strict_types=1);

namespace DalPraS\Payment\Entity;

use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\Customer;
use DalPraS\Payment\ValueObject\LineItem;

final class Payment
{
    /** @param list<LineItem> $items */
    public function __construct(
        private string $reference,
        private string $merchantReference,
        private string $providerCode,
        private PaymentIntent $intent,
        private PaymentStatus $status,
        private Customer $customer,
        private array $items,
        private AmountBreakdown $amounts,
        private ?string $providerPaymentId = null,
        private ?string $providerToken = null,
        private ?string $idempotencyKey = null,
        private ?string $correlationId = null,
        private array $metadata = [],
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->updatedAt ??= new \DateTimeImmutable();
    }

    public function reference(): string { return $this->reference; }
    public function merchantReference(): string { return $this->merchantReference; }
    public function providerCode(): string { return $this->providerCode; }
    public function intent(): PaymentIntent { return $this->intent; }
    public function status(): PaymentStatus { return $this->status; }
    public function customer(): Customer { return $this->customer; }
    /** @return list<LineItem> */ public function items(): array { return $this->items; }
    public function amounts(): AmountBreakdown { return $this->amounts; }
    public function providerPaymentId(): ?string { return $this->providerPaymentId; }
    public function providerToken(): ?string { return $this->providerToken; }
    public function idempotencyKey(): ?string { return $this->idempotencyKey; }
    public function correlationId(): ?string { return $this->correlationId; }
    public function metadata(): array { return $this->metadata; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function setProviderPaymentId(?string $providerPaymentId): void
    {
        $this->providerPaymentId = $providerPaymentId;
        $this->touch();
    }

    public function setProviderToken(?string $providerToken): void
    {
        $this->providerToken = $providerToken;
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'merchantReference' => $this->merchantReference,
            'providerCode' => $this->providerCode,
            'intent' => $this->intent->value,
            'status' => $this->status->value,
            'customer' => $this->customer->toArray(),
            'items' => array_map(static fn(LineItem $item) => $item->toArray(), $this->items),
            'amounts' => $this->amounts->toArray(),
            'providerPaymentId' => $this->providerPaymentId,
            'providerToken' => $this->providerToken,
            'idempotencyKey' => $this->idempotencyKey,
            'correlationId' => $this->correlationId,
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
