<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\Architecture\ThinLayerBoundaryCop;
use PHPuboCop\Cop\Architecture\ThinLayerComplexityCop;
use PHPuboCop\Cop\Architecture\ThinLayerForbiddenFunctionsCop;
use PHPuboCop\Cop\Architecture\ThinLayerGlobalStateUsageCop;
use PHPuboCop\Cop\Architecture\ThinLayerIncludeUsageCop;
use PHPuboCop\Cop\Architecture\ThinLayerForbiddenMethodCallsCop;
use PHPuboCop\Cop\Architecture\ThinLayerForbiddenStaticCallsCop;
use PHPuboCop\Cop\Architecture\ThinLayerLengthCop;
use PHPuboCop\Cop\Architecture\ThinLayerSuperglobalUsageCop;
use PHPuboCop\Cop\Layout\LineLengthCop;
use PHPuboCop\Cop\Layout\IndentationStyleCop;
use PHPuboCop\Cop\Layout\TrailingWhitespaceCop;
use PHPuboCop\Cop\Layout\TrailingCommaInMultilineCop;
use PHPuboCop\Cop\Lint\DuplicateArrayKeyCop;
use PHPuboCop\Cop\Lint\DuplicateMethodCop;
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
use PHPuboCop\Cop\Style\BooleanLiteralComparisonCop;
use PHPuboCop\Cop\Style\DoubleQuotesCop;
use PHPuboCop\Cop\Style\EmptyCatchCop;
use PHPuboCop\Cop\Style\StrictComparisonCop;
use PHPuboCop\Cop\Security\UnserializeCop;
use PHPuboCop\Cop\Security\ExecCop;
use PHPuboCop\Cop\Security\EvalAndDynamicIncludeCop;

final class CopRegistry
{
    public static function default(): array
    {
        return [
            new TrailingWhitespaceCop(),
            new TrailingCommaInMultilineCop(),
            new LineLengthCop(),
            new IndentationStyleCop(),
            new DuplicateArrayKeyCop(),
            new DuplicateMethodCop(),
            new EvalUsageCop(),
            new SuppressedErrorCop(),
            new ShadowingVariableCop(),
            new UnreachableCodeCop(),
            new UselessAssignmentCop(),
            new UnusedVariableCop(),
            new DoubleQuotesCop(),
            new BooleanLiteralComparisonCop(),
            new EmptyCatchCop(),
            new StrictComparisonCop(),
            new AbcSizeCop(),
            new CyclomaticComplexityCop(),
            new MethodLengthCop(),
            new PerceivedComplexityCop(),
            new ParameterListsCop(),
            new UnserializeCop(),
            new ExecCop(),
            new EvalAndDynamicIncludeCop(),
            new ThinLayerBoundaryCop(),
            new ThinLayerComplexityCop(),
            new ThinLayerLengthCop(),
            new ThinLayerSuperglobalUsageCop(),
            new ThinLayerGlobalStateUsageCop(),
            new ThinLayerIncludeUsageCop(),
            new ThinLayerForbiddenFunctionsCop(),
            new ThinLayerForbiddenMethodCallsCop(),
            new ThinLayerForbiddenStaticCallsCop(),
        ];
    }
}
