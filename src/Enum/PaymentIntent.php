<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum PaymentIntent: string
{
    case SALE = 'sale';
    case AUTHORIZE = 'authorize';
    case CAPTURE_LATER = 'capture_later';
}
