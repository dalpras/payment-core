<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum Currency: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Gbp = 'GBP';
}
