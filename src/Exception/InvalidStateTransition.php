<?php declare(strict_types=1);

namespace DalPraS\Payment\Exception;

use DalPraS\Payment\Enum\PaymentStatus;

final class InvalidStateTransition extends PaymentException
{
    public static function from(PaymentStatus $from, PaymentStatus $to): self
    {
        return new self(sprintf('Invalid state transition from "%s" to "%s".', $from->value, $to->value));
    }
}
