<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\Layout\LineLengthCop;
use PHPuboCop\Cop\Layout\TrailingWhitespaceCop;
use PHPuboCop\Cop\Lint\DuplicateArrayKeyCop;
use PHPuboCop\Cop\Lint\EvalUsageCop;
use PHPuboCop\Cop\Lint\UnreachableCodeCop;
use PHPuboCop\Cop\Lint\UnusedVariableCop;
use PHPuboCop\Cop\Metrics\AbcSizeCop;
use PHPuboCop\Cop\Metrics\CyclomaticComplexityCop;
use PHPuboCop\Cop\Metrics\MethodLengthCop;
use PHPuboCop\Cop\Metrics\PerceivedComplexityCop;
use PHPuboCop\Cop\Style\DoubleQuotesCop;

final class CopRegistry
{
    public static function default(): array
    {
        return [
            new TrailingWhitespaceCop(),
            new LineLengthCop(),
            new DuplicateArrayKeyCop(),
            new EvalUsageCop(),
            new UnreachableCodeCop(),
            new UnusedVariableCop(),
            new DoubleQuotesCop(),
            new AbcSizeCop(),
            new CyclomaticComplexityCop(),
            new MethodLengthCop(),
            new PerceivedComplexityCop(),
        ];
    }
}
