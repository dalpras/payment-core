<?php declare(strict_types=1);

namespace DalPraS\Payment\Adapter;

use DalPraS\Payment\Contract\PaymentCalculatorInterface;
use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\LineItem;
use DalPraS\Payment\ValueObject\Money;

final class SimpleLineItemCalculator implements PaymentCalculatorInterface
{
    public function calculate(array $items): AmountBreakdown
    {
        if ($items === []) {
            throw new \InvalidArgumentException('At least one item is required.');
        }

        $currency = $items[0]->unitPrice->currency();
        $subtotal = Money::zero($currency);
        $tax = Money::zero($currency);
        $discount = Money::zero($currency);
        foreach ($items as $item) {
            $subtotal = $subtotal->plus($item->unitPrice->multiply($item->quantity));
            if ($item->taxAmount !== null) {
                $tax = $tax->plus($item->taxAmount);
            }
            if ($item->discountAmount !== null) {
                $discount = $discount->plus($item->discountAmount);
            }
        }
        $shipping = Money::zero($currency);
        return new AmountBreakdown($subtotal, $tax, $discount, $shipping, $subtotal->plus($tax)->minus($discount)->plus($shipping));
    }
}
