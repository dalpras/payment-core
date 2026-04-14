<?php declare(strict_types=1);

namespace DalPraS\Payment\Contract;

use DalPraS\Payment\ValueObject\AmountBreakdown;
use DalPraS\Payment\ValueObject\LineItem;

interface PaymentCalculatorInterface
{
    /** @param list<LineItem> $items */
    public function calculate(array $items): AmountBreakdown;
}
