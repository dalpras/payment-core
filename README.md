# DalPraS Payment Core

A provider-agnostic PHP payment library skeleton designed to replace legacy Omnipay-style integrations with a modern, explicit, extensible architecture.

It is intentionally split into:
- a **core package** with provider-neutral contracts and value objects
- **provider connectors** such as PayPal and Nexi implemented separately
- optional framework bridges for Laminas or other frameworks

## Goals

- support PayPal, Nexi, and future providers through native connectors
- keep business entities decoupled from payment SDKs
- model money, items, totals, checkout, completion, capture, refund, and sync explicitly
- make retries, callbacks, and reconciliation idempotent
- allow different purchasable objects to be adapted into a common payment model

## Package status

This package is a **skeleton** intended as a strong starting point.
It already contains:
- contracts
- DTOs
- enums
- value objects
- repository interfaces and in-memory implementations
- an idempotency contract and in-memory store
- a payment manager
- a provider registry
- a normalized payment aggregate
- a basic state machine

It does **not** yet contain concrete connector implementations for PayPal or Nexi. Those should live in:
- `dalpras/payment-paypal`
- `dalpras/payment-nexi`

## Installation

```bash
composer require dalpras/payment-core
```

## Namespace

```php
DalPraS\Payment\
```

## High-level architecture

### Core concepts

- `Money`: immutable monetary value in minor units
- `LineItem`: normalized item to buy
- `AmountBreakdown`: subtotal, tax, discount, shipping, total
- `Customer`, `Address`: snapshots used for checkout
- `Payment`: normalized payment aggregate
- `PaymentOperation`: normalized provider interaction log
- `PaymentProviderInterface`: provider-neutral connector contract
- `PaymentManager`: orchestration service
- `PurchasableAdapterInterface`: adapts domain objects into payment inputs
- `PaymentCalculatorInterface`: computes totals from adapted items
- `PaymentRepositoryInterface`: persists payments and operations
- `IdempotencyStoreInterface`: protects external operations from duplication

### Suggested split into packages

- `dalpras/payment-core`
- `dalpras/payment-paypal`
- `dalpras/payment-nexi`
- `dalpras/payment-laminas`

## Example flow

```php
use DalPraS\Payment\Manager\PaymentManager;
use DalPraS\Payment\Registry\ProviderRegistry;
use DalPraS\Payment\Repository\InMemoryPaymentRepository;
use DalPraS\Payment\Idempotency\InMemoryIdempotencyStore;

$manager = new PaymentManager(
    new ProviderRegistry(),
    new InMemoryPaymentRepository(),
    new InMemoryIdempotencyStore(),
);
```

A real application would:
1. adapt a domain object through `PurchasableAdapterInterface`
2. calculate totals through `PaymentCalculatorInterface`
3. create a `CheckoutRequest`
4. call `PaymentManager::createCheckout()`
5. store the returned redirect URL and provider payment ID
6. handle browser return through `completeCheckout()`
7. handle webhooks separately through connector-specific parsing and verification

## Core interfaces

### PaymentProviderInterface

```php
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
```

## Adapting business objects

Keep domain entities clean. Prefer adapters instead of making domain models implement payment interfaces directly.

Example idea:

```php
final class VatCrmOrderAdapter implements PurchasableAdapterInterface
{
    public function supports(object $subject): bool
    {
        return $subject instanceof VatCrmOrderEntity;
    }

    public function toPaymentDraft(object $subject): PaymentDraft
    {
        // map domain order to normalized customer, items, totals, metadata
    }
}
```

## State model

Normalized payment statuses:
- `draft`
- `pending_redirect`
- `pending_customer_action`
- `authorized`
- `captured`
- `partially_captured`
- `failed`
- `cancelled`
- `refunded`
- `partially_refunded`
- `expired`
- `unknown`

## What to build next

### `dalpras/payment-paypal`
Implement `PaymentProviderInterface` using PayPal Orders v2.

### `dalpras/payment-nexi`
Implement `PaymentProviderInterface` using Nexi official APIs / SDK.

### `dalpras/payment-laminas`
Add controller helpers, URL builders, dependency configuration, and HTTP entrypoints.

## Testing

This skeleton includes in-memory implementations so you can start writing tests before choosing persistence and provider SDKs.

## License

MIT
