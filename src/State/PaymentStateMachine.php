<?php declare(strict_types=1);

namespace DalPraS\Payment\State;

use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\Exception\InvalidStateTransition;

final class PaymentStateMachine
{
    public function assertCanTransition(PaymentStatus $from, PaymentStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        if (!in_array($to, $this->allowedTransitions($from), true)) {
            throw InvalidStateTransition::from($from, $to);
        }
    }

    /**
     * @return list<PaymentStatus>
     */
    private function allowedTransitions(PaymentStatus $from): array
    {
        return match ($from) {
            PaymentStatus::Draft => [
                PaymentStatus::PendingRedirect,
                PaymentStatus::PendingCustomerAction,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ],

            PaymentStatus::PendingRedirect => [
                PaymentStatus::PendingCustomerAction,
                PaymentStatus::Captured,
                PaymentStatus::Authorized,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
                PaymentStatus::Expired,
            ],

            PaymentStatus::PendingCustomerAction => [
                PaymentStatus::Captured,
                PaymentStatus::Authorized,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
                PaymentStatus::Expired,
            ],

            PaymentStatus::Authorized => [
                PaymentStatus::Captured,
                PaymentStatus::PartiallyCaptured,
                PaymentStatus::Cancelled,
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
            ],

            PaymentStatus::Captured => [
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
            ],

            PaymentStatus::PartiallyCaptured => [
                PaymentStatus::Captured,
                PaymentStatus::PartiallyRefunded,
                PaymentStatus::Refunded,
            ],

            PaymentStatus::PartiallyRefunded => [
                PaymentStatus::Refunded,
            ],

            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
            PaymentStatus::Refunded,
            PaymentStatus::Expired => [],

            PaymentStatus::Unknown => [
                PaymentStatus::PendingCustomerAction,
                PaymentStatus::Captured,
                PaymentStatus::Authorized,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ],
        };
    }
}