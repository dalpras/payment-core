<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum OperationType: string
{
    case CHECKOUT_CREATE = 'checkout_create';
    case CHECKOUT_COMPLETE = 'checkout_complete';
    case AUTHORIZE = 'authorize';
    case CAPTURE = 'capture';
    case CANCEL = 'cancel';
    case REFUND = 'refund';
    case SYNC = 'sync';
    case WEBHOOK = 'webhook';
}
