<?php declare(strict_types=1);

namespace DalPraS\Payment\Repository;

use DalPraS\Payment\Contract\PaymentRepositoryInterface;
use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Symfony/PSR-6 cache-backed payment repository.
 *
 * This repository keeps PaymentManager state available across HTTP requests
 * during redirect-based flows:
 *
 *   createCheckout() -> redirect to Nexi/PayPal -> completeCheckout()
 *
 * It is suitable when your application already uses Symfony Cache pools,
 * for example RedisAdapter, FilesystemAdapter, or another PSR-6 pool.
 *
 * Important:
 * - this is not intended to be the long-term accounting source of truth;
 * - persist final status/provider metadata in your application entity/table;
 * - for Redis-specific atomic operations, RedisPaymentRepository is still the
 *   lower-level option.
 */
final class CachePaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $prefix = 'payment_repository_',
        private readonly ?int $ttlSeconds = 86400,
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->saveValue(
            key: $this->paymentKey($payment->reference()),
            value: $payment,
        );

        // Refresh the operation history TTL too, if it already exists.
        $this->refreshTtl($this->operationsKey($payment->reference()));
    }

    public function get(string $reference): ?Payment
    {
        $value = $this->getValue($this->paymentKey($reference));

        return $value instanceof Payment ? $value : null;
    }

    public function addOperation(PaymentOperation $operation): void
    {
        $key = $this->operationsKey($operation->paymentReference());

        $operations = $this->getOperationsValue($key);
        $operations[] = $operation;

        $this->saveValue($key, $operations);

        // An operation usually means the payment is still relevant, so keep the
        // related payment alive for the same checkout-flow window.
        $this->refreshTtl($this->paymentKey($operation->paymentReference()));
    }

    /**
     * @return list<PaymentOperation>
     */
    public function operationsFor(string $paymentReference): array
    {
        return $this->getOperationsValue($this->operationsKey($paymentReference));
    }

    private function saveValue(string $key, mixed $value): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value);

        if ($this->ttlSeconds !== null && $this->ttlSeconds > 0) {
            $item->expiresAfter($this->ttlSeconds);
        }

        $this->cache->save($item);
    }

    private function getValue(string $key): mixed
    {
        $item = $this->cache->getItem($key);

        if (! $item->isHit()) {
            return null;
        }

        return $item->get();
    }

    /**
     * @return list<PaymentOperation>
     */
    private function getOperationsValue(string $key): array
    {
        $value = $this->getValue($key);

        if (! is_array($value)) {
            return [];
        }

        $operations = [];

        foreach ($value as $operation) {
            if ($operation instanceof PaymentOperation) {
                $operations[] = $operation;
            }
        }

        return array_values($operations);
    }

    private function refreshTtl(string $key): void
    {
        if ($this->ttlSeconds === null || $this->ttlSeconds <= 0) {
            return;
        }

        $item = $this->cache->getItem($key);

        if (! $item->isHit()) {
            return;
        }

        $item->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);
    }

    private function paymentKey(string $reference): string
    {
        return $this->prefix . 'payment_' . hash('sha256', $reference);
    }

    private function operationsKey(string $paymentReference): string
    {
        return $this->prefix . 'operations_' . hash('sha256', $paymentReference);
    }
}