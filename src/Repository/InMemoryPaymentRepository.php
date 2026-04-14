<?php declare(strict_types=1);

namespace DalPraS\Payment\Repository;

use DalPraS\Payment\Contract\PaymentRepositoryInterface;
use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;

final class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    /** @var array<string, Payment> */
    private array $payments = [];
    /** @var array<string, list<PaymentOperation>> */
    private array $operations = [];

    public function save(Payment $payment): void
    {
        $this->payments[$payment->reference()] = $payment;
    }

    public function get(string $reference): ?Payment
    {
        return $this->payments[$reference] ?? null;
    }

    public function addOperation(PaymentOperation $operation): void
    {
        $this->operations[$operation->paymentReference()] ??= [];
        $this->operations[$operation->paymentReference()][] = $operation;
    }

    public function operationsFor(string $paymentReference): array
    {
        return $this->operations[$paymentReference] ?? [];
    }
}
