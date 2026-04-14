<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum PaymentStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REDIRECT = 'pending_redirect';
    case PENDING_CUSTOMER_ACTION = 'pending_customer_action';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case PARTIALLY_CAPTURED = 'partially_captured';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case EXPIRED = 'expired';
    case UNKNOWN = 'unknown';
}
