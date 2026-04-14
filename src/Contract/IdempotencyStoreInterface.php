<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

interface IdempotencyStoreInterface
{
    public function has(string $key): bool;
    public function get(string $key): mixed;
    public function put(string $key, mixed $value): void;
}
