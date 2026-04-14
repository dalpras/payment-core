<?php declare(strict_types=1);

namespace DalPraS\Payment\Idempotency;

use DalPraS\Payment\Contract\IdempotencyStoreInterface;

final class InMemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function put(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}
