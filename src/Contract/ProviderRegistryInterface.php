<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

interface ProviderRegistryInterface
{
    public function add(PaymentProviderInterface $provider): void;
    public function get(string $code): PaymentProviderInterface;
    /** @return list<string> */
    public function codes(): array;
}
