<?php declare(strict_types=1);

namespace DalPraS\Payment\Dto;

final class VerificationResult
{
    public function __construct(
        public readonly bool $verified,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }
}
