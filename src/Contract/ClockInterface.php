<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
