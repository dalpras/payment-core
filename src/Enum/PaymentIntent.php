<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum PaymentIntent: string
{
    case Sale = 'sale';
    case Authorize = 'authorize';
    case CaptureLater = 'capture_later';
}
