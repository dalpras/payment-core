<?php declare(strict_types=1);

namespace DalPraS\Payment\State;

use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\Exception\InvalidStateTransition;

final class PaymentStateMachine
{
    /** @var array<string, list<PaymentStatus>> */
    private const MAP = [
        'draft' => [PaymentStatus::PendingRedirect, PaymentStatus::PendingCustomerAction, PaymentStatus::Failed, PaymentStatus::Cancelled],
        'pending_redirect' => [PaymentStatus::PendingCustomerAction, PaymentStatus::Captured, PaymentStatus::Authorized, PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired],
        'pending_customer_action' => [PaymentStatus::Captured, PaymentStatus::Authorized, PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired],
        'authorized' => [PaymentStatus::Captured, PaymentStatus::PartiallyCaptured, PaymentStatus::Cancelled, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded],
        'captured' => [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded],
        'partially_captured' => [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded],
        'partially_refunded' => [PaymentStatus::Refunded],
        'failed' => [],
        'cancelled' => [],
        'refunded' => [],
        'expired' => [],
        'unknown' => [PaymentStatus::PendingCustomerAction, PaymentStatus::Captured, PaymentStatus::Authorized, PaymentStatus::Failed, PaymentStatus::Cancelled],
    ];

    public function assertCanTransition(PaymentStatus $from, PaymentStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = self::MAP[$from->value] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw InvalidStateTransition::from($from, $to);
        }
    }
}
