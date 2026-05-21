<?php declare(strict_types=1);

namespace DalPraS\Payment\Manager;

use DalPraS\Payment\Contract\IdempotencyStoreInterface;
use DalPraS\Payment\Contract\PaymentRepositoryInterface;
use DalPraS\Payment\Contract\ProviderRegistryInterface;
use DalPraS\Payment\Dto\AuthorizationResult;
use DalPraS\Payment\Dto\AuthorizeRequest;
use DalPraS\Payment\Dto\CancelRequest;
use DalPraS\Payment\Dto\CancelResult;
use DalPraS\Payment\Dto\CaptureRequest;
use DalPraS\Payment\Dto\CaptureResult;
use DalPraS\Payment\Dto\CheckoutRequest;
use DalPraS\Payment\Dto\CheckoutResponse;
use DalPraS\Payment\Dto\CompletionRequest;
use DalPraS\Payment\Dto\CompletionResult;
use DalPraS\Payment\Dto\OperationResult;
use DalPraS\Payment\Dto\RefundRequest;
use DalPraS\Payment\Dto\RefundResult;
use DalPraS\Payment\Dto\SyncRequest;
use DalPraS\Payment\Dto\SyncResult;
use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;
use DalPraS\Payment\Enum\OperationType;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\State\PaymentStateMachine;

/**
 * Coordinates provider operations, idempotency, state transitions and metadata reuse.
 *
 * Provider connectors return normalized metadata such as operation_id, capture_id,
 * authorization_id, nexi_order_id, or paypal_order_id. The manager persists that
 * metadata and injects it into later requests, keeping application adapters focused
 * on business data instead of provider-specific lifecycle identifiers.
 */
final class PaymentManager
{
    public function __construct(
        private readonly ProviderRegistryInterface $providers,
        private readonly PaymentRepositoryInterface $payments,
        private readonly IdempotencyStoreInterface $idempotency,
        private readonly PaymentStateMachine $stateMachine = new PaymentStateMachine(),
    ) {
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        $idempotencyKey = $this->operationIdempotencyKey(OperationType::CheckoutCreate, $request->idempotencyKey);

        if ($idempotencyKey !== null && $this->idempotency->has($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);
            if (!$cached instanceof CheckoutResponse) {
                throw $this->invalidCachedResult($idempotencyKey, CheckoutResponse::class, $cached);
            }
            return $cached;
        }

        $provider = $this->providers->get($request->providerCode);

        $payment = new Payment(
            reference: $request->paymentReference,
            merchantReference: $request->merchantReference,
            providerCode: $request->providerCode,
            intent: $request->intent,
            status: PaymentStatus::Draft,
            customer: $request->customer,
            items: $request->items,
            amounts: $request->amounts,
            idempotencyKey: $request->idempotencyKey,
            correlationId: $request->correlationId,
            metadata: $request->metadata,
        );

        $response = $provider->createCheckout($request);

        $this->stateMachine->assertCanTransition($payment->status(), $response->status);

        $payment->setStatus($response->status);
        $payment->setProviderPaymentId($response->providerPaymentId);
        $payment->setProviderToken($response->providerToken);
        $payment->mergeMetadata($response->metadata);

        $this->payments->save($payment);
        $this->recordOperation(
            payment: $payment,
            type: OperationType::CheckoutCreate,
            status: $response->status,
            providerPaymentId: $response->providerPaymentId,
            raw: $response->raw,
            message: $response->message,
            metadata: $response->metadata,
        );

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $response);
        }

        return $response;
    }

    public function completeCheckout(CompletionRequest $request): CompletionResult
    {
        $idempotencyKey = $this->operationIdempotencyKey(OperationType::CheckoutComplete, $request->idempotencyKey);

        if ($idempotencyKey !== null && $this->idempotency->has($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);
            if (!$cached instanceof CompletionResult) {
                throw $this->invalidCachedResult($idempotencyKey, CompletionResult::class, $cached);
            }
            return $cached;
        }

        $payment = $this->payments->get($request->paymentReference);
        $request = $this->enrichCompletionRequest($request, $payment);
        $provider = $this->providers->get($request->providerCode);

        $result = $provider->completeCheckout($request);

        if (!$result instanceof CompletionResult) {
            throw new \UnexpectedValueException(sprintf(
                'Provider "%s" returned invalid completeCheckout result: expected %s, got %s.',
                $request->providerCode,
                CompletionResult::class,
                is_object($result) ? $result::class : gettype($result)
            ));
        }

        if ($payment !== null) {
            $this->applyResult($payment, OperationType::CheckoutComplete, $result);

            $forcedCaptureResult = $this->captureAfterAuthorizationIfRequired($request, $payment, $result);
            if ($forcedCaptureResult !== null) {
                $result = $forcedCaptureResult;
            }
        }

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $result);
        }

        return $result;
    }

    public function authorize(AuthorizeRequest $request): AuthorizationResult
    {
        return $this->runSimple(
            $request,
            OperationType::Authorize,
            AuthorizationResult::class,
            fn ($provider, AuthorizeRequest $enriched): AuthorizationResult => $provider->authorize($enriched)
        );
    }

    public function capture(CaptureRequest $request): CaptureResult
    {
        return $this->runSimple(
            $request,
            OperationType::Capture,
            CaptureResult::class,
            fn ($provider, CaptureRequest $enriched): CaptureResult => $provider->capture($enriched)
        );
    }

    public function cancel(CancelRequest $request): CancelResult
    {
        return $this->runSimple(
            $request,
            OperationType::Cancel,
            CancelResult::class,
            fn ($provider, CancelRequest $enriched): CancelResult => $provider->cancel($enriched)
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return $this->runSimple(
            $request,
            OperationType::Refund,
            RefundResult::class,
            fn ($provider, RefundRequest $enriched): RefundResult => $provider->refund($enriched)
        );
    }

    public function sync(SyncRequest $request): SyncResult
    {
        return $this->runSimple(
            $request,
            OperationType::Sync,
            SyncResult::class,
            fn ($provider, SyncRequest $enriched): SyncResult => $provider->sync($enriched)
        );
    }

    /**
     * @template T of OperationResult
     * @param object $request
     * @param class-string<T> $expectedClass
     * @param callable(object, object): T $callback
     * @return T
     */
    private function runSimple(
        object $request,
        OperationType $type,
        string $expectedClass,
        callable $callback
    ): OperationResult {
        $rawIdempotencyKey = $request->idempotencyKey ?? null;
        $idempotencyKey = $this->operationIdempotencyKey($type, $rawIdempotencyKey);

        if ($idempotencyKey !== null && $this->idempotency->has($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);
            if (!$cached instanceof $expectedClass) {
                throw $this->invalidCachedResult($idempotencyKey, $expectedClass, $cached);
            }
            return $cached;
        }

        $payment = $this->payments->get($request->paymentReference);
        $request = $this->enrichSimpleRequest($request, $payment, $type);
        $provider = $this->providers->get($request->providerCode);

        $result = $callback($provider, $request);

        if (!$result instanceof $expectedClass) {
            throw new \UnexpectedValueException(sprintf(
                'Provider "%s" returned invalid result for operation "%s": expected %s, got %s.',
                $request->providerCode,
                $type->value,
                $expectedClass,
                is_object($result) ? $result::class : gettype($result)
            ));
        }

        if ($payment !== null) {
            $this->applyResult($payment, $type, $result);
        }

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $result);
        }

        return $result;
    }

    /** Apply a provider result to the aggregate and persist a matching operation log. */
    private function applyResult(Payment $payment, OperationType $type, OperationResult $result): void
    {
        $this->stateMachine->assertCanTransition($payment->status(), $result->status);

        $payment->setStatus($result->status);
        $payment->setProviderPaymentId($result->providerPaymentId ?? $payment->providerPaymentId());
        $payment->mergeMetadata($result->metadata);

        $this->payments->save($payment);
        $this->recordOperation(
            payment: $payment,
            type: $type,
            status: $result->status,
            providerPaymentId: $result->providerPaymentId,
            raw: $result->raw,
            message: $result->message,
            transactionIds: $result->transactionIds,
            metadata: $result->metadata,
        );
    }

    private function recordOperation(
        Payment $payment,
        OperationType $type,
        PaymentStatus $status,
        ?string $providerPaymentId = null,
        array $raw = [],
        ?string $message = null,
        array $transactionIds = [],
        array $metadata = [],
    ): void {
        $this->payments->addOperation(new PaymentOperation(
            paymentReference: $payment->reference(),
            type: $type,
            status: $status,
            providerPaymentId: $providerPaymentId,
            raw: $raw,
            message: $message,
            transactionIds: $transactionIds,
            metadata: $metadata,
        ));
    }

    /**
     * Enrich browser-return completion with the stored provider order/payment id.
     *
     * This prevents controllers from having to know whether a provider expects a Nexi
     * orderId, a PayPal token/order id, or another identifier after redirect.
     */
    private function enrichCompletionRequest(CompletionRequest $request, ?Payment $payment): CompletionRequest
    {
        if ($payment === null) {
            return $request;
        }

        $metadata = $this->mergedOperationMetadata($payment, $request->metadata);

        return new CompletionRequest(
            providerCode: $request->providerCode,
            paymentReference: $request->paymentReference,
            queryParams: $request->queryParams,
            bodyParams: $request->bodyParams,
            expectedProviderPaymentId: $request->expectedProviderPaymentId
                ?? $payment->providerPaymentId()
                ?? $this->firstString($metadata, ['provider_payment_id', 'order_id', 'nexi_order_id', 'paypal_order_id']),
            expectedIntent: $request->expectedIntent ?? $payment->intent(),
            idempotencyKey: $request->idempotencyKey,
            metadata: $metadata,
            correlationId: $request->correlationId ?? $payment->correlationId(),
        );
    }

    /**
     * Merge stored metadata into post-checkout requests before invoking providers.
     *
     * Explicit request metadata wins, then latest operation metadata, then payment
     * metadata. This makes manual overrides possible while keeping the default path
     * automatic for refund/cancel/capture/sync.
     */
    private function enrichSimpleRequest(object $request, ?Payment $payment, OperationType $type): object
    {
        if ($payment === null) {
            return $request;
        }

        $requestMetadata = is_array($request->metadata ?? null) ? $request->metadata : [];
        $metadata = $this->mergedOperationMetadata($payment, $requestMetadata);
        $providerPaymentId = ($request->providerPaymentId ?? null) ?: $this->resolveProviderPaymentId($payment, $metadata, $type);

        return match ($request::class) {
            AuthorizeRequest::class => new AuthorizeRequest(
                providerCode: $request->providerCode,
                paymentReference: $request->paymentReference,
                providerPaymentId: $providerPaymentId,
                idempotencyKey: $request->idempotencyKey,
                metadata: $metadata,
            ),
            CaptureRequest::class => new CaptureRequest(
                providerCode: $request->providerCode,
                paymentReference: $request->paymentReference,
                providerPaymentId: $providerPaymentId,
                idempotencyKey: $request->idempotencyKey,
                metadata: $metadata,
            ),
            CancelRequest::class => new CancelRequest(
                providerCode: $request->providerCode,
                paymentReference: $request->paymentReference,
                providerPaymentId: $providerPaymentId,
                idempotencyKey: $request->idempotencyKey,
                metadata: $metadata,
            ),
            RefundRequest::class => new RefundRequest(
                providerCode: $request->providerCode,
                paymentReference: $request->paymentReference,
                providerPaymentId: $providerPaymentId,
                idempotencyKey: $request->idempotencyKey,
                metadata: $metadata,
            ),
            SyncRequest::class => new SyncRequest(
                providerCode: $request->providerCode,
                paymentReference: $request->paymentReference,
                providerPaymentId: $providerPaymentId,
                idempotencyKey: $request->idempotencyKey,
                metadata: $metadata,
            ),
            default => $request,
        };
    }

    /**
     * When enabled, turn an authorization returned by completion into a real capture.
     *
     * Some providers/terminals may return Authorized even for a sale-style checkout.
     * The policy is opt-in through metadata/providerOptions persisted on the Payment:
     * force_capture_after_authorization=true.
     */
    private function captureAfterAuthorizationIfRequired(
        CompletionRequest $request,
        Payment $payment,
        CompletionResult $completionResult,
    ): ?CompletionResult {
        if ($completionResult->status !== PaymentStatus::Authorized) {
            return null;
        }

        $metadata = array_replace_recursive(
            $this->mergedOperationMetadata($payment, $request->metadata),
            $completionResult->metadata,
        );

        if (!($metadata['force_capture_after_authorization'] ?? false)) {
            return null;
        }

        $operationId = $this->firstString($metadata, [
            'operation_id',
            'nexi_operation_id',
            'authorization_id',
            'paypal_authorization_id',
            'provider_payment_id',
        ]) ?? $completionResult->providerPaymentId;

        if ($operationId === null || $operationId === '') {
            return null;
        }

        $captureMetadata = array_replace_recursive($metadata, [
            'description' => $metadata['capture_description'] ?? 'Forced capture after authorization',
        ]);

        $captureResult = $this->capture(new CaptureRequest(
            providerCode: $request->providerCode,
            paymentReference: $request->paymentReference,
            providerPaymentId: $operationId,
            idempotencyKey: $this->deterministicUuid($request->paymentReference . ':capture'),
            metadata: $captureMetadata,
        ));

        return new CompletionResult(
            status: $captureResult->status,
            providerPaymentId: $captureResult->providerPaymentId ?? $operationId,
            transactionIds: $captureResult->transactionIds,
            message: $captureResult->message,
            raw: [
                'completion' => $completionResult->raw,
                'capture' => $captureResult->raw,
            ],
            metadata: array_replace_recursive(
                $completionResult->metadata,
                $captureResult->metadata,
            ),
        );
    }

    private function mergedOperationMetadata(Payment $payment, array $requestMetadata): array
    {
        return array_replace_recursive(
            $payment->metadata(),
            $this->latestOperationMetadata($payment->reference()),
            $requestMetadata,
        );
    }

    private function latestOperationMetadata(string $paymentReference): array
    {
        $operations = array_reverse($this->payments->operationsFor($paymentReference));

        foreach ($operations as $operation) {
            $metadata = $operation->metadata();
            if ($metadata !== []) {
                return $metadata;
            }
        }

        return [];
    }

    /** Resolve the provider identifier most likely needed for the given operation. */
    private function resolveProviderPaymentId(Payment $payment, array $metadata, OperationType $type): ?string
    {
        return match ($type) {
            OperationType::Refund => $this->firstString($metadata, [
                'capture_id', 'paypal_capture_id', 'operation_id', 'nexi_operation_id', 'nexi_capture_operation_id',
            ]) ?? $payment->providerPaymentId(),
            OperationType::Cancel => $this->firstString($metadata, [
                'operation_id', 'nexi_operation_id', 'nexi_capture_operation_id', 'authorization_id', 'paypal_authorization_id',
            ]) ?? $payment->providerPaymentId(),
            OperationType::Capture => $this->firstString($metadata, [
                'authorization_id', 'paypal_authorization_id', 'operation_id', 'nexi_operation_id', 'nexi_authorization_operation_id',
            ]) ?? $payment->providerPaymentId(),
            OperationType::Sync => $this->firstString($metadata, [
                'order_id', 'nexi_order_id', 'paypal_order_id', 'provider_payment_id',
            ]) ?? $payment->providerPaymentId(),
            default => $payment->providerPaymentId(),
        };
    }

    /** @param list<string> $keys */
    private function firstString(array $metadata, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function operationIdempotencyKey(OperationType $type, ?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        return $type->value . ':' . $idempotencyKey;
    }

    private function invalidCachedResult(
        string $idempotencyKey,
        string $expectedClass,
        mixed $cached
    ): \UnexpectedValueException {
        return new \UnexpectedValueException(sprintf(
            'Invalid cached idempotency value for key "%s": expected %s, got %s.',
            $idempotencyKey,
            $expectedClass,
            is_object($cached) ? $cached::class : gettype($cached)
        ));
    }

    private function deterministicUuid(string $value): string
    {
        $hash = md5($value, true);

        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x30); // UUID v3
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($hash), 4));
    }    
}
