<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum PaymentStatus: string
{
    case Draft = 'draft';
    case PendingRedirect = 'pending_redirect';
    case PendingCustomerAction = 'pending_customer_action';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case PartiallyCaptured = 'partially_captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Expired = 'expired';
    case Unknown = 'unknown';
}
