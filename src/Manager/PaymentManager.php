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
use DalPraS\Payment\Dto\RefundRequest;
use DalPraS\Payment\Dto\RefundResult;
use DalPraS\Payment\Dto\SyncRequest;
use DalPraS\Payment\Dto\SyncResult;
use DalPraS\Payment\Entity\Payment;
use DalPraS\Payment\Entity\PaymentOperation;
use DalPraS\Payment\Enum\OperationType;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\State\PaymentStateMachine;

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
        if ($request->idempotencyKey !== null && $this->idempotency->has($request->idempotencyKey)) {
            /** @var CheckoutResponse $cached */
            $cached = $this->idempotency->get($request->idempotencyKey);
            return $cached;
        }

        $provider = $this->providers->get($request->providerCode);
        $payment = new Payment(
            reference: $request->paymentReference,
            merchantReference: $request->merchantReference,
            providerCode: $request->providerCode,
            intent: $request->intent,
            status: PaymentStatus::DRAFT,
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
        $this->payments->addOperation(new PaymentOperation($payment->reference(), OperationType::CHECKOUT_CREATE, $response->status, $response->providerPaymentId, $response->raw, $response->message));

        if ($request->idempotencyKey !== null) {
            $this->idempotency->put($request->idempotencyKey, $response);
        }

        return $response;
    }

    public function completeCheckout(CompletionRequest $request): CompletionResult
    {
        if ($request->idempotencyKey !== null && $this->idempotency->has($request->idempotencyKey)) {
            /** @var CompletionResult $cached */
            $cached = $this->idempotency->get($request->idempotencyKey);
            return $cached;
        }

        $payment = $this->payments->get($request->paymentReference);
        $provider = $this->providers->get($request->providerCode);
        $result = $provider->completeCheckout($request);
        if ($payment !== null) {
            $this->stateMachine->assertCanTransition($payment->status(), $result->status);
            $payment->setStatus($result->status);
            $payment->setProviderPaymentId($result->providerPaymentId ?? $payment->providerPaymentId());
            $this->payments->save($payment);
            $this->payments->addOperation(new PaymentOperation($payment->reference(), OperationType::CHECKOUT_COMPLETE, $result->status, $result->providerPaymentId, $result->raw, $result->message));
        }
        if ($request->idempotencyKey !== null) {
            $this->idempotency->put($request->idempotencyKey, $result);
        }
        return $result;
    }

    public function authorize(AuthorizeRequest $request): AuthorizationResult { return $this->runSimple($request, OperationType::AUTHORIZE, fn($p) => $p->authorize($request)); }
    public function capture(CaptureRequest $request): CaptureResult { return $this->runSimple($request, OperationType::CAPTURE, fn($p) => $p->capture($request)); }
    public function cancel(CancelRequest $request): CancelResult { return $this->runSimple($request, OperationType::CANCEL, fn($p) => $p->cancel($request)); }
    public function refund(RefundRequest $request): RefundResult { return $this->runSimple($request, OperationType::REFUND, fn($p) => $p->refund($request)); }
    public function sync(SyncRequest $request): SyncResult { return $this->runSimple($request, OperationType::SYNC, fn($p) => $p->sync($request)); }

    private function runSimple(object $request, OperationType $type, callable $callback): mixed
    {
        $idempotencyKey = $request->idempotencyKey ?? null;
        if ($idempotencyKey !== null && $this->idempotency->has($idempotencyKey)) {
            return $this->idempotency->get($idempotencyKey);
        }
        $provider = $this->providers->get($request->providerCode);
        $payment = $this->payments->get($request->paymentReference);
        $result = $callback($provider);
        if ($payment !== null && $result instanceof \DalPraS\Payment\Dto\OperationResult) {
            $this->stateMachine->assertCanTransition($payment->status(), $result->status);
            $payment->setStatus($result->status);
            $payment->setProviderPaymentId($result->providerPaymentId ?? $payment->providerPaymentId());
            $this->payments->save($payment);
            $this->payments->addOperation(new PaymentOperation($payment->reference(), $type, $result->status, $result->providerPaymentId, $result->raw, $result->message));
        }
        if ($idempotencyKey !== null) {
            $this->idempotency->put($idempotencyKey, $result);
        }
        return $result;
    }
}
