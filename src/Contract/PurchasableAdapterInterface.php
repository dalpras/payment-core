<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

use DalPraS\Payment\Dto\PaymentDraft;

interface PurchasableAdapterInterface
{
    public function supports(object $subject): bool;
    public function toPaymentDraft(object $subject): PaymentDraft;
}
