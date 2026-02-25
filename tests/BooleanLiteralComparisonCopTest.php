<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Style\BooleanLiteralComparisonCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class BooleanLiteralComparisonCopTest extends TestCase
{
    public function testReportsComparisonsToTrueOrFalse(): void
    {
        $cop = new BooleanLiteralComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if ($ok === true) {
    return;
}

if (false != $ready) {
    return;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
        self::assertSame('Style/BooleanLiteralComparison', $offenses[0]->copName);
    }

    public function testDoesNotReportFalseChecksForKnownFalseableFunctionResultVariable(): void
    {
        $cop = new BooleanLiteralComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$deliveryTimesJson = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($deliveryTimesJson === false) {
    $deliveryTimesJson = '{}';
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testDoesNotReportDirectFalseChecksForKnownFalseableFunctions(): void
    {
        $cop = new BooleanLiteralComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if (strpos($s, 'x') === false) {
    return;
}

if (preg_match($re, $s) === false) {
    return;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testDoesNotReportNonBooleanComparisons(): void
    {
        $cop = new BooleanLiteralComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if ($count === 0) {
    return;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
