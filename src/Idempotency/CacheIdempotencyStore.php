<?php declare(strict_types=1);

namespace DalPraS\Payment\Idempotency;

use DalPraS\Payment\Contract\IdempotencyStoreInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CacheIdempotencyStore implements IdempotencyStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ?int $ttlSeconds = 86400,
        private readonly string $prefix = 'payment_idempotency_',
    ) {
    }

    public function has(string $key): bool
    {
        return $this->cache->hasItem($this->cacheKey($key));
    }

    public function get(string $key): mixed
    {
        $item = $this->cache->getItem($this->cacheKey($key));

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function put(string $key, mixed $value): void
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        $item->set($value);

        if ($this->ttlSeconds !== null && $this->ttlSeconds > 0) {
            $item->expiresAfter($this->ttlSeconds);
        }

        $this->cache->save($item);
    }

    private function cacheKey(string $key): string
    {
        return $this->prefix . hash('sha256', $key);
    }
}