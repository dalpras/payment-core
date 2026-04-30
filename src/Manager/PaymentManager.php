<?php declare(strict_types=1);

namespace DalPraS\Payment\Manager;

use DalPraS\Payment\Contract\IdempotencyStoreInterface;
use DalPraS\Payment\Contract\PaymentRepositoryInterface;
use DalPraS\Payment\Contract\ProviderRegistryInterface;
use DalPraS\Payment\Dto\AuthorizeRequest;
use DalPraS\Payment\Dto\AuthorizationResult;
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
 * Coordinates payment operations between the application, payment providers,
 * the payment repository, the state machine and the idempotency store.
 *
 * The manager is responsible for:
 *
 * - resolving the correct payment provider;
 * - creating and updating Payment entities;
 * - validating payment status transitions;
 * - storing PaymentOperation history records;
 * - applying idempotency to avoid duplicated provider calls.
 *
 * Idempotency keys are scoped by operation type before being stored.
 * This prevents different operations from sharing the same cached result.
 *
 * For example, the same raw idempotency key may become:
 *
 * - checkout_create:abc123
 * - checkout_complete:abc123
 * - capture:abc123
 * - refund:abc123
 *
 * This avoids returning a CheckoutResponse from completeCheckout(),
 * or a CaptureResult from refund(), when the same raw key is reused by callers.
 */
final class PaymentManager
{
    /**
     * @param ProviderRegistryInterface $providers Registry used to resolve the provider by provider code.
     * @param PaymentRepositoryInterface $payments Repository used to persist payments and operations.
     * @param IdempotencyStoreInterface $idempotency Store used to cache operation results by scoped idempotency key.
     * @param PaymentStateMachine $stateMachine State machine used to validate payment status transitions.
     */
    public function __construct(
        private readonly ProviderRegistryInterface $providers,
        private readonly PaymentRepositoryInterface $payments,
        private readonly IdempotencyStoreInterface $idempotency,
        private readonly PaymentStateMachine $stateMachine = new PaymentStateMachine(),
    ) {
    }

    /**
     * Creates a checkout session/order with the selected provider.
     *
     * This method creates a new local Payment entity in Draft status,
     * calls the provider to create the checkout, validates the transition
     * to the provider response status, stores the provider identifiers,
     * persists the payment and records a CheckoutCreate operation.
     *
     * If an idempotency key is provided, the cached CheckoutResponse is
     * returned on repeated calls with the same scoped key.
     */
    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        $idempotencyKey = $this->operationIdempotencyKey(
            OperationType::CheckoutCreate,
            $request->idempotencyKey
        );

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

        $this->payments->save($payment);

        $this->payments->addOperation(new PaymentOperation(
            $payment->reference(),
            OperationType::CheckoutCreate,
            $response->status,
            $response->providerPaymentId,
            $response->raw,
            $response->message
        ));

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $response);
        }

        return $response;
    }

    /**
     * Completes a previously created checkout after the customer returns
     * from the provider checkout flow.
     *
     * This method calls the provider completion endpoint, updates the local
     * Payment entity when found, validates the status transition and records
     * a CheckoutComplete operation.
     *
     * Typical examples:
     *
     * - capturing or confirming a PayPal order after payer approval;
     * - finalizing a hosted checkout result;
     * - reading provider-side completion status after redirect.
     *
     * If an idempotency key is provided, the cached CompletionResult is
     * returned on repeated calls with the same scoped key.
     */
    public function completeCheckout(CompletionRequest $request): CompletionResult
    {
        $idempotencyKey = $this->operationIdempotencyKey(
            OperationType::CheckoutComplete,
            $request->idempotencyKey
        );

        if ($idempotencyKey !== null && $this->idempotency->has($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);

            if (!$cached instanceof CompletionResult) {
                throw $this->invalidCachedResult($idempotencyKey, CompletionResult::class, $cached);
            }

            return $cached;
        }

        $payment = $this->payments->get($request->paymentReference);
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
            $this->stateMachine->assertCanTransition($payment->status(), $result->status);

            $payment->setStatus($result->status);
            $payment->setProviderPaymentId(
                $result->providerPaymentId ?? $payment->providerPaymentId()
            );

            $this->payments->save($payment);

            $this->payments->addOperation(new PaymentOperation(
                $payment->reference(),
                OperationType::CheckoutComplete,
                $result->status,
                $result->providerPaymentId,
                $result->raw,
                $result->message
            ));
        }

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $result);
        }

        return $result;
    }

    /**
     * Authorizes a payment without capturing funds immediately.
     *
     * The provider performs an authorization and the local payment status
     * is updated according to the provider result.
     */
    public function authorize(AuthorizeRequest $request): AuthorizationResult
    {
        return $this->runSimple(
            $request,
            OperationType::Authorize,
            AuthorizationResult::class,
            fn ($provider): AuthorizationResult => $provider->authorize($request)
        );
    }

    /**
     * Captures a previously authorized payment.
     *
     * The provider captures the funds and the local payment status is updated
     * according to the provider result.
     */
    public function capture(CaptureRequest $request): CaptureResult
    {
        return $this->runSimple(
            $request,
            OperationType::Capture,
            CaptureResult::class,
            fn ($provider): CaptureResult => $provider->capture($request)
        );
    }

    /**
     * Cancels or voids a payment, depending on the provider and current status.
     *
     * The local payment status is updated after the provider confirms the
     * cancellation result.
     */
    public function cancel(CancelRequest $request): CancelResult
    {
        return $this->runSimple(
            $request,
            OperationType::Cancel,
            CancelResult::class,
            fn ($provider): CancelResult => $provider->cancel($request)
        );
    }

    /**
     * Refunds a payment, either fully or partially depending on the request.
     *
     * The local payment status is updated according to the refund result
     * returned by the provider.
     */
    public function refund(RefundRequest $request): RefundResult
    {
        return $this->runSimple(
            $request,
            OperationType::Refund,
            RefundResult::class,
            fn ($provider): RefundResult => $provider->refund($request)
        );
    }

    /**
     * Synchronizes the local payment state with the provider state.
     *
     * This is useful when the provider sends asynchronous updates, when a
     * webhook is missed, or when the application needs to verify the latest
     * provider-side payment status.
     */
    public function sync(SyncRequest $request): SyncResult
    {
        return $this->runSimple(
            $request,
            OperationType::Sync,
            SyncResult::class,
            fn ($provider): SyncResult => $provider->sync($request)
        );
    }

    /**
     * Executes a common provider operation and applies the standard payment
     * update flow.
     *
     * This helper is used by authorize, capture, cancel, refund and sync.
     * It resolves the provider, applies operation-scoped idempotency, executes
     * the provider callback, validates the expected result type, updates the
     * local Payment entity when found, records the PaymentOperation and stores
     * the result in the idempotency store.
     *
     * @template T of OperationResult
     *
     * @param object $request Operation request DTO. It must expose providerCode, paymentReference and optionally idempotencyKey.
     * @param OperationType $type Operation type used for history and idempotency scoping.
     * @param class-string<T> $expectedClass Expected result DTO class.
     * @param callable(object): T $callback Callback that executes the provider operation.
     *
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

        $provider = $this->providers->get($request->providerCode);
        $payment = $this->payments->get($request->paymentReference);

        $result = $callback($provider);

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
            $this->stateMachine->assertCanTransition($payment->status(), $result->status);

            $payment->setStatus($result->status);
            $payment->setProviderPaymentId(
                $result->providerPaymentId ?? $payment->providerPaymentId()
            );

            $this->payments->save($payment);

            $this->payments->addOperation(new PaymentOperation(
                $payment->reference(),
                $type,
                $result->status,
                $result->providerPaymentId,
                $result->raw,
                $result->message
            ));
        }

        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $result);
        }

        return $result;
    }

    /**
     * Builds an operation-scoped idempotency key.
     *
     * Raw idempotency keys should not be stored directly because the same
     * caller-provided key may be reused across different payment operations.
     * Prefixing the key with the operation type ensures that each operation
     * has an independent cached result.
     */
    private function operationIdempotencyKey(OperationType $type, ?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        return $type->value . ':' . $idempotencyKey;
    }

    /**
     * Creates a descriptive exception for an idempotency cache type mismatch.
     *
     * A mismatch usually means the same raw idempotency key was previously
     * stored without operation scoping, or the idempotency store contains
     * stale data from an older implementation.
     *
     * @param class-string $expectedClass
     */
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
}