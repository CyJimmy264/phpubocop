<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\Layout\LineLengthCop;
use PHPuboCop\Cop\Layout\TrailingWhitespaceCop;
use PHPuboCop\Cop\Lint\DuplicateArrayKeyCop;
use PHPuboCop\Cop\Lint\EvalUsageCop;
use PHPuboCop\Cop\Lint\SuppressedErrorCop;
use PHPuboCop\Cop\Lint\ShadowingVariableCop;
use PHPuboCop\Cop\Lint\UnreachableCodeCop;
use PHPuboCop\Cop\Lint\UselessAssignmentCop;
use PHPuboCop\Cop\Lint\UnusedVariableCop;
use PHPuboCop\Cop\Metrics\AbcSizeCop;
use PHPuboCop\Cop\Metrics\CyclomaticComplexityCop;
use PHPuboCop\Cop\Metrics\MethodLengthCop;
use PHPuboCop\Cop\Metrics\PerceivedComplexityCop;
use PHPuboCop\Cop\Metrics\ParameterListsCop;
use PHPuboCop\Cop\Style\DoubleQuotesCop;
use PHPuboCop\Cop\Style\EmptyCatchCop;
use PHPuboCop\Cop\Security\UnserializeCop;
use PHPuboCop\Cop\Security\ExecCop;

final class CopRegistry
{
    public static function default(): array
    {
        return [
            new TrailingWhitespaceCop(),
            new LineLengthCop(),
            new DuplicateArrayKeyCop(),
            new EvalUsageCop(),
            new SuppressedErrorCop(),
            new ShadowingVariableCop(),
            new UnreachableCodeCop(),
            new UselessAssignmentCop(),
            new UnusedVariableCop(),
            new DoubleQuotesCop(),
            new EmptyCatchCop(),
            new AbcSizeCop(),
            new CyclomaticComplexityCop(),
            new MethodLengthCop(),
            new PerceivedComplexityCop(),
            new ParameterListsCop(),
            new UnserializeCop(),
            new ExecCop(),
        ];
    }
}
