<?php declare(strict_types=1);

namespace DalPraS\Payment\Repository;

use DalPraS\Payment\Contract\PaymentRepositoryInterface;
use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;
use Redis;

/**
 * Redis-backed payment repository for redirect-based checkout flows.
 *
 * This repository is intentionally a lifecycle/state repository, not a long-term
 * accounting ledger. It keeps PaymentManager state available across HTTP requests
 * during flows such as:
 *
 *   createCheckout() -> redirect to provider -> completeCheckout()
 *
 * Use a durable database entity/table as the final source of truth for completed
 * payments, provider metadata and audit/reporting data. Redis is ideal for the
 * active checkout window and as a safer production replacement for
 * InMemoryPaymentRepository.
 */
final class RedisPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'payment:repository:',
        private readonly ?int $ttlSeconds = 86400,
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->put($this->paymentKey($payment->reference()), $payment);

        // Keep operation history alive at least as long as the payment itself.
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

        $this->redis->rPush($key, serialize($operation));
        $this->refreshTtl($key);

        // An operation usually means the payment is still relevant. Refresh the
        // related payment TTL too, if it exists.
        $this->refreshTtl($this->paymentKey($operation->paymentReference()));
    }

    /** @return list<PaymentOperation> */
    public function operationsFor(string $paymentReference): array
    {
        $values = $this->redis->lRange($this->operationsKey($paymentReference), 0, -1);

        if ($values === false || $values === []) {
            return [];
        }

        $operations = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $operation = unserialize($value, ['allowed_classes' => true]);

            if ($operation instanceof PaymentOperation) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    private function put(string $key, mixed $value): void
    {
        $serialized = serialize($value);

        if ($this->ttlSeconds !== null && $this->ttlSeconds > 0) {
            $this->redis->setex($key, $this->ttlSeconds, $serialized);
            return;
        }

        $this->redis->set($key, $serialized);
    }

    private function getValue(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false || ! is_string($value)) {
            return null;
        }

        return unserialize($value, ['allowed_classes' => true]);
    }

    private function refreshTtl(string $key): void
    {
        if ($this->ttlSeconds === null || $this->ttlSeconds <= 0) {
            return;
        }

        if ($this->redis->exists($key) > 0) {
            $this->redis->expire($key, $this->ttlSeconds);
        }
    }

    private function paymentKey(string $reference): string
    {
        return $this->prefix . 'payment:' . hash('sha256', $reference);
    }

    private function operationsKey(string $paymentReference): string
    {
        return $this->prefix . 'operations:' . hash('sha256', $paymentReference);
    }
}
