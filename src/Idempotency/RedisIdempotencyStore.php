<?php declare(strict_types=1);

namespace DalPraS\Payment\Idempotency;

use DalPraS\Payment\Contract\IdempotencyStoreInterface;
use Redis;

final class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'payment:idempotency:',
        private readonly ?int $ttlSeconds = 86400,
    ) {
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->redisKey($key)) > 0;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->redisKey($key));

        if ($value === false) {
            return null;
        }

        return unserialize($value, ['allowed_classes' => true]);
    }

    public function put(string $key, mixed $value): void
    {
        $serialized = serialize($value);
        $redisKey = $this->redisKey($key);

        if ($this->ttlSeconds !== null && $this->ttlSeconds > 0) {
            $this->redis->setex($redisKey, $this->ttlSeconds, $serialized);
            return;
        }

        $this->redis->set($redisKey, $serialized);
    }

    private function redisKey(string $key): string
    {
        return $this->prefix . hash('sha256', $key);
    }
}