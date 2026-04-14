<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

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
use Psr\Http\Message\ServerRequestInterface;

interface PaymentProviderInterface
{
    public function code(): string;
    public function createCheckout(CheckoutRequest $request): CheckoutResponse;
    public function completeCheckout(CompletionRequest $request): CompletionResult;
    public function authorize(AuthorizeRequest $request): AuthorizationResult;
    public function capture(CaptureRequest $request): CaptureResult;
    public function cancel(CancelRequest $request): CancelResult;
    public function refund(RefundRequest $request): RefundResult;
    public function sync(SyncRequest $request): SyncResult;
    public function parseWebhook(ServerRequestInterface $request): WebhookEvent;
    public function verifyWebhook(WebhookEvent $event): VerificationResult;
}
