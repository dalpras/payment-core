<?php declare(strict_types=1);

namespace DalPraS\Payment\Tests;

use DalPraS\Payment\Enum\Currency;
use DalPraS\Payment\Enum\PaymentIntent;
use DalPraS\Payment\Enum\PaymentStatus;
use DalPraS\Payment\Idempotency\InMemoryIdempotencyStore;
use DalPraS\Payment\Manager\PaymentManager;
use DalPraS\Payment\Provider\NullProvider;
use DalPraS\Payment\Registry\ProviderRegistry;
use DalPraS\Payment\Repository\InMemoryPaymentRepository;
use DalPraS\Payment\Dto\CheckoutRequest;
use DalPraS\Payment\Dto\CompletionRequest;
use DalPraS\Payment\ValueObject\Address;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\Customer;
use DalPraS\Payment\ValueObject\LineItem;
use DalPraS\Payment\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PaymentManagerTest extends TestCase
{
    public function test_checkout_and_completion_flow(): void
    {
        $registry = new ProviderRegistry();
        $registry->add(new NullProvider('paypal'));
        $repository = new InMemoryPaymentRepository();
        $manager = new PaymentManager($registry, $repository, new InMemoryIdempotencyStore());

        $item = new LineItem('SKU1', 'Test item', 2, Money::fromDecimal('10.00', Currency::EUR));
        $amounts = new AmountBreakdown(
            subtotal: Money::fromDecimal('20.00', Currency::EUR),
            taxTotal: Money::zero(Currency::EUR),
            discountTotal: Money::zero(Currency::EUR),
            shippingTotal: Money::zero(Currency::EUR),
            grandTotal: Money::fromDecimal('20.00', Currency::EUR),
        );

        $checkout = $manager->createCheckout(new CheckoutRequest(
            providerCode: 'paypal',
            paymentReference: 'pay_1',
            merchantReference: 'order_1',
            customer: new Customer('cust_1', 'user@example.com', 'Test User', new Address(fullName: 'Test User')),
            items: [$item],
            amounts: $amounts,
            returnUrl: 'https://example.test/return',
            cancelUrl: 'https://example.test/cancel',
            intent: PaymentIntent::SALE,
        ));

        self::assertTrue($checkout->redirectRequired);
        self::assertSame(PaymentStatus::PendingRedirect, $repository->get('pay_1')?->status());

        $complete = $manager->completeCheckout(new CompletionRequest('paypal', 'pay_1'));
        self::assertSame(PaymentStatus::Captured, $complete->status);
        self::assertSame(PaymentStatus::Captured, $repository->get('pay_1')?->status());
    }
}
