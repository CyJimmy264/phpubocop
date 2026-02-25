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
