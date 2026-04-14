<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;
    public function get(string $reference): ?Payment;
    public function addOperation(PaymentOperation $operation): void;
    /** @return list<PaymentOperation> */
    public function operationsFor(string $paymentReference): array;
}
