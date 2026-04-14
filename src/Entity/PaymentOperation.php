<?php declare(strict_types=1);

namespace DalPraS\Payment\Entity;

use DalPraS\Payment\Enum\OperationType;
use DalPraS\Payment\Enum\PaymentStatus;

final class PaymentOperation
{
    public function __construct(
        private string $paymentReference,
        private OperationType $type,
        private PaymentStatus $status,
        private ?string $providerPaymentId = null,
        private array $raw = [],
        private ?string $message = null,
        private ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function paymentReference(): string { return $this->paymentReference; }
    public function type(): OperationType { return $this->type; }
    public function status(): PaymentStatus { return $this->status; }
    public function providerPaymentId(): ?string { return $this->providerPaymentId; }
    public function raw(): array { return $this->raw; }
    public function message(): ?string { return $this->message; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}
