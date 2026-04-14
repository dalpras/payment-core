<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

interface UrlGeneratorInterface
{
    public function returnUrl(string $paymentReference): string;
    public function cancelUrl(string $paymentReference): string;
    public function webhookUrl(string $paymentReference): string;
}
