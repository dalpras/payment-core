<?php declare(strict_types=1);

namespace DalPraS\Payment\Exception;

final class ProviderNotFound extends PaymentException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Payment provider "%s" not found.', $code));
    }
}
