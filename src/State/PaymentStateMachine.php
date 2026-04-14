<?php declare(strict_types=1);

namespace DalPraS\Payment\State;

use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\Exception\InvalidStateTransition;

final class PaymentStateMachine
{
    /** @var array<string, list<PaymentStatus>> */
    private const MAP = [
        'draft' => [PaymentStatus::PENDING_REDIRECT, PaymentStatus::PENDING_CUSTOMER_ACTION, PaymentStatus::FAILED, PaymentStatus::CANCELLED],
        'pending_redirect' => [PaymentStatus::PENDING_CUSTOMER_ACTION, PaymentStatus::CAPTURED, PaymentStatus::AUTHORIZED, PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED],
        'pending_customer_action' => [PaymentStatus::CAPTURED, PaymentStatus::AUTHORIZED, PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED],
        'authorized' => [PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_CAPTURED, PaymentStatus::CANCELLED, PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED],
        'captured' => [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED],
        'partially_captured' => [PaymentStatus::CAPTURED, PaymentStatus::PARTIALLY_REFUNDED, PaymentStatus::REFUNDED],
        'partially_refunded' => [PaymentStatus::REFUNDED],
        'failed' => [],
        'cancelled' => [],
        'refunded' => [],
        'expired' => [],
        'unknown' => [PaymentStatus::PENDING_CUSTOMER_ACTION, PaymentStatus::CAPTURED, PaymentStatus::AUTHORIZED, PaymentStatus::FAILED, PaymentStatus::CANCELLED],
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
