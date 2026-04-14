<?php declare(strict_types=1);

namespace DalPraS\Payment\Registry;

use DalPraS\Payment\Contract\PaymentProviderInterface;
use DalPraS\Payment\Contract\ProviderRegistryInterface;
use DalPraS\Payment\Exception\ProviderNotFound;

final class ProviderRegistry implements ProviderRegistryInterface
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    public function add(PaymentProviderInterface $provider): void
    {
        $this->providers[$provider->code()] = $provider;
    }

    public function get(string $code): PaymentProviderInterface
    {
        return $this->providers[$code] ?? throw ProviderNotFound::forCode($code);
    }

    public function codes(): array
    {
        return array_keys($this->providers);
    }
}
