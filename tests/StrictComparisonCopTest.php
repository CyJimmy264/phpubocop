<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Style\StrictComparisonCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class StrictComparisonCopTest extends TestCase
{
    public function testReportsLooseComparisons(): void
    {
        $cop = new StrictComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if ($a == $b) {
    return;
}

if ($c != $d) {
    return;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
        self::assertSame('Style/StrictComparison', $offenses[0]->copName);
    }

    public function testDoesNotReportStrictComparisons(): void
    {
        $cop = new StrictComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if ($a === $b) {
    return;
}

if ($c !== $d) {
    return;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testAutocorrectFixesSafeLiteralLooseComparisons(): void
    {
        $cop = new StrictComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if (1 == 2) {
    return;
}
if ('a' != 'b') {
    return;
}
PHP,
);

        $fixed = $cop->autocorrect($source);

        self::assertStringContainsString('1 === 2', $fixed);
        self::assertStringContainsString("'a' !== 'b'", $fixed);
    }

    public function testAutocorrectSkipsPotentiallyUnsafeVariableComparisons(): void
    {
        $cop = new StrictComparisonCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
if ($a == $b) {
    return;
}
PHP,
);

        $fixed = $cop->autocorrect($source);

        self::assertSame($source->content, $fixed);
    }
}
