<?php declare(strict_types=1);

namespace DalPraS\Payment\Provider;

use DalPraS\Payment\Contract\PaymentProviderInterface;
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
use DalPraS\Payment\Dto\VerificationResult;
use DalPraS\Payment\Dto\WebhookEvent;
use DalPraS\Payment\Enum\PaymentStatus;
use Psr\Http\Message\ServerRequestInterface;

final class NullProvider implements PaymentProviderInterface
{
    public function __construct(private readonly string $providerCode = 'null') {}
    public function code(): string { return $this->providerCode; }
    public function createCheckout(CheckoutRequest $request): CheckoutResponse { return new CheckoutResponse(PaymentStatus::PendingRedirect, true, 'https://example.test/checkout/'.$request->paymentReference, 'prov_'.$request->paymentReference, null, null, ['provider' => $this->providerCode], 'Stub checkout created'); }
    public function completeCheckout(CompletionRequest $request): CompletionResult { return new CompletionResult(PaymentStatus::Captured, 'prov_'.$request->paymentReference, ['tx_'.$request->paymentReference], 'Stub checkout completed', ['provider' => $this->providerCode]); }
    public function authorize(AuthorizeRequest $request): AuthorizationResult { return new AuthorizationResult(PaymentStatus::Authorized, $request->providerPaymentId, [], 'Stub authorize'); }
    public function capture(CaptureRequest $request): CaptureResult { return new CaptureResult(PaymentStatus::Captured, $request->providerPaymentId, [], 'Stub capture'); }
    public function cancel(CancelRequest $request): CancelResult { return new CancelResult(PaymentStatus::Cancelled, $request->providerPaymentId, [], 'Stub cancel'); }
    public function refund(RefundRequest $request): RefundResult { return new RefundResult(PaymentStatus::Refunded, $request->providerPaymentId, [], 'Stub refund'); }
    public function sync(SyncRequest $request): SyncResult { return new SyncResult(PaymentStatus::Unknown, $request->providerPaymentId, [], 'Stub sync'); }
    public function parseWebhook(ServerRequestInterface $request): WebhookEvent { return new WebhookEvent($this->providerCode, 'stub.webhook', null, []); }
    public function verifyWebhook(WebhookEvent $event): VerificationResult { return new VerificationResult(true, 'Stub verification'); }
}
