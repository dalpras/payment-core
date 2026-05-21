# DalPraS Payment Core

Provider-agnostic PHP payment orchestration for checkout, completion, capture, refund, cancel and reconciliation.

The package is designed to keep application code independent from provider-specific identifiers while still supporting real payment lifecycles such as:

- Nexi Hosted Payment Page: `orderId` for checkout/completion, `operationId` for capture/refund/cancel
- PayPal Orders v2: order id for checkout/completion, capture id for refund, authorization id for delayed capture/void

## Goals

- Keep business entities decoupled from payment SDKs and provider payloads.
- Persist a normalized `Payment` aggregate and immutable `PaymentOperation` audit log.
- Make browser returns, retries, provider API calls and reconciliation idempotent.
- Let provider packages return normalized metadata once, then let core reuse it automatically.
- Allow application adapters to provide business metadata without knowing provider lifecycle IDs.

## Package status

This package contains:

- DTOs and value objects for checkout and provider operations
- `PaymentProviderInterface`
- `PaymentManager`
- provider registry
- payment repository contract with in-memory and Redis implementations
- idempotency contract and in-memory/cache/Redis implementations
- normalized `Payment` aggregate
- immutable `PaymentOperation` audit entries
- basic state machine

Concrete providers live in separate packages:

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

## Core lifecycle

```text
Application adapter
    -> CheckoutRequest with business data and business metadata
PaymentManager::createCheckout()
    -> provider creates order/session
    -> provider returns providerPaymentId, providerToken and normalized metadata
    -> core stores Payment and PaymentOperation
Browser return / webhook / admin action
    -> application calls completeCheckout(), sync(), capture(), refund(), or cancel()
    -> core enriches the request with stored Payment metadata and latest operation metadata
    -> provider resolves the correct provider ID and performs the operation
    -> core stores the new result metadata for future operations
```

## Metadata model

`Payment::$metadata` is intentionally part of the orchestration model. It contains:

1. **Application metadata** supplied by your adapter.
2. **Provider checkout metadata** returned by providers.
3. **Provider operation metadata** returned by completion/capture/refund/cancel/sync.

Common normalized keys:

| Key | Meaning |
| --- | --- |
| `provider` | Provider code such as `nexi` or `paypal` |
| `provider_payment_id` | Main provider order/payment identifier |
| `order_id` | Generic provider order id |
| `operation_id` | Generic operation id for providers such as Nexi |
| `capture_id` | Generic capture id, used by PayPal refunds |
| `authorization_id` | Generic authorization id, used by delayed capture/void |
| `nexi_order_id` | Nexi HPP order id |
| `nexi_operation_id` | Nexi operation id |
| `paypal_order_id` | PayPal order id |
| `paypal_capture_id` | PayPal capture id |
| `paypal_authorization_id` | PayPal authorization id |

Request-level metadata always wins over stored metadata, so applications can still override the selected provider identifier when needed.

## Important DTOs

### `CheckoutResponse`

Providers return `metadata` here to persist identifiers discovered at checkout creation.

```php
new CheckoutResponse(
    status: PaymentStatus::PendingCustomerAction,
    redirectRequired: true,
    redirectUrl: $redirectUrl,
    providerPaymentId: $providerOrderId,
    providerToken: $token,
    raw: $rawProviderResponse,
    metadata: [
        'provider_payment_id' => $providerOrderId,
        'order_id' => $providerOrderId,
    ],
);
```

### `OperationResult`

All operation results carry `transactionIds` and `metadata`. Provider packages should normalize useful IDs here instead of making the application parse `raw`.

```php
new RefundResult(
    status: PaymentStatus::Refunded,
    providerPaymentId: $captureOrOperationId,
    transactionIds: [$refundOperationId],
    raw: $rawProviderResponse,
    metadata: [
        'capture_id' => $captureId,
        'refund_id' => $refundOperationId,
    ],
);
```

## PaymentManager enrichment

`PaymentManager` automatically enriches requests before invoking providers:

- `completeCheckout()` receives stored provider order/payment id when the browser return does not include it.
- `refund()` receives the latest stored `capture_id`, `operation_id`, or provider-specific equivalent.
- `cancel()` receives the latest stored `operation_id` or `authorization_id`.
- `capture()` receives the latest stored `authorization_id` or provider-specific equivalent.
- `sync()` receives the stored order/payment id.

This means application code can usually call:

```php
$result = $paymentManager->refund(new RefundRequest(
    providerCode: 'nexi',
    paymentReference: $paymentReference,
    providerPaymentId: null,
    idempotencyKey: $refundId,
    metadata: [
        'amount_minor' => '5000',
        'currency' => 'EUR',
        'description' => 'Customer refund',
    ],
));
```

Core will merge the stored Nexi `operation_id` before calling the Nexi provider.

## Application adapter guidance

Application adapters should provide business data, not provider-generated IDs.

Good adapter metadata:

```php
metadata: [
    'application' => 'my-shop',
    'local_order_id' => (string) $order->id(),
    'order_number' => $order->number(),
    'payment_uuid' => $paymentReference,
    'description' => 'Order ' . $order->number(),
    'amount_minor' => (string) $order->grandTotalMinor(),
    'amount_decimal' => $order->grandTotalDecimal(),
    'currency' => $order->currencyCode(),
]
```

Provider packages then add provider metadata after API calls.

## Redis payment repository

`RedisPaymentRepository` is the recommended production replacement for `InMemoryPaymentRepository` when you need a lightweight cross-request repository for redirect-based providers.

It stores the `Payment` aggregate and related `PaymentOperation` entries in Redis using PHP serialization and a configurable TTL. This lets `PaymentManager` recover provider metadata after the customer returns from Nexi/PayPal:

```text
createCheckout() -> RedisPaymentRepository::save() -> provider redirect -> completeCheckout() -> RedisPaymentRepository::get()
```

Example Symfony wiring:

```yaml
DalPraS\Payment\Repository\RedisPaymentRepository:
  class: DalPraS\Payment\Repository\RedisPaymentRepository
  arguments:
    - '@redis'
    - 'payment:repository:'
    - 86400
  public: false

DalPraS\Payment\Contract\PaymentRepositoryInterface:
  alias: DalPraS\Payment\Repository\RedisPaymentRepository
  public: false
```

Use a TTL long enough for abandoned browser sessions and delayed redirects. `86400` seconds, or 24 hours, is a practical default.

Redis should still be treated as active-flow storage. For final accounting, support, reporting and later refunds/cancels, persist important provider metadata in your own durable entity/table, for example `OrderEntity.paymentMetadata`.

## Persistence notes

If you replace `InMemoryPaymentRepository` with a durable database repository, persist at least:

### Payment

- reference
- merchant reference
- provider code
- intent
- status
- customer snapshot
- line items snapshot
- amount breakdown
- provider payment id
- provider token
- idempotency key
- correlation id
- metadata JSON
- created/updated timestamps

### PaymentOperation

- payment reference
- operation type
- status
- provider payment id
- transaction ids JSON
- metadata JSON
- raw payload JSON
- message
- created timestamp

The new metadata and transaction id fields are important. Without them, automatic refund/cancel/capture enrichment will lose provider lifecycle identifiers.

## Basic usage

```php
use DalPraS\Payment\Idempotency\InMemoryIdempotencyStore;
use DalPraS\Payment\Manager\PaymentManager;
use DalPraS\Payment\Registry\ProviderRegistry;
use DalPraS\Payment\Repository\InMemoryPaymentRepository;
use DalPraS\Payment\Repository\RedisPaymentRepository;

$registry = new ProviderRegistry();
$registry->register($paypalProvider);
$registry->register($nexiProvider);

$manager = new PaymentManager(
    providers: $registry,
    payments: new InMemoryPaymentRepository(), // Replace with RedisPaymentRepository or a DB repository in production.
    idempotency: new InMemoryIdempotencyStore(),
);
```


Production-style repository example:

```php
$manager = new PaymentManager(
    providers: $registry,
    payments: new RedisPaymentRepository($redis, 'payment:repository:', 86400),
    idempotency: new InMemoryIdempotencyStore(),
);
```

## Testing

Run syntax checks:

```bash
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run PHPUnit after installing development dependencies:

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
