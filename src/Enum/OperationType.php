<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum OperationType: string
{
    case CheckoutCreate = 'checkout_create';
    case CheckoutComplete = 'checkout_complete';
    case Authorize = 'authorize';
    case Capture = 'capture';
    case Cancel = 'cancel';
    case Refund = 'refund';
    case Sync = 'sync';
    case Webhook = 'webhook';
}
