<?php declare(strict_types=1);

namespace DalPraS\Payment\Enum;

enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case NZD = 'NZD';
    case SEK = 'SEK';
    case NOK = 'NOK';
    case DKK = 'DKK';
    case PLN = 'PLN';
    case CZK = 'CZK';
    case HUF = 'HUF';
    case RON = 'RON';
    case BGN = 'BGN';
    case CNY = 'CNY';
    case HKD = 'HKD';
    case SGD = 'SGD';
    case INR = 'INR';
    case BRL = 'BRL';
    case MXN = 'MXN';
    case ZAR = 'ZAR';
    case KWD = 'KWD';
    case BHD = 'BHD';
    case OMR = 'OMR';
    case TND = 'TND';

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::USD,
            self::CAD,
            self::AUD,
            self::NZD,
            self::HKD,
            self::SGD,
            self::MXN => '$',
            self::GBP => '£',
            self::CHF => 'CHF',
            self::JPY,
            self::CNY => '¥',
            self::SEK,
            self::NOK,
            self::DKK => 'kr',
            self::CZK => 'Kč',
            self::PLN => 'zł',
            self::HUF => 'Ft',
            self::RON => 'lei',
            self::BGN => 'лв',
            self::INR => '₹',
            self::BRL => 'R$',
            self::ZAR => 'R',
            self::KWD => 'د.ك',
            self::BHD => '.د.ب',
            self::OMR => 'ر.ع.',
            self::TND => 'د.ت',
        };
    }

    public function fractionDigits(): int
    {
        return match ($this) {
            self::JPY => 0,
            self::KWD,
            self::BHD,
            self::OMR,
            self::TND => 3,
            default => 2,
        };
    }
}
