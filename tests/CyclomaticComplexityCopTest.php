<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\CyclomaticComplexityCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class CyclomaticComplexityCopTest extends TestCase
{
    public function testReportsWhenComplexityExceedsLimit(): void
    {
        $cop = new CyclomaticComplexityCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function decide($a, $b, $c) {
    if ($a > 0 && $b > 0) {
        return 1;
    } elseif ($c > 0) {
        return 2;
    }

    foreach ([$a, $b, $c] as $value) {
        if ($value < 0) {
            return 3;
        }
    }

    return 0;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 4]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/CyclomaticComplexity', $offenses[0]->copName);
        self::assertStringContainsString('decide', $offenses[0]->message);
    }

    public function testDoesNotReportWhenWithinLimit(): void
    {
        $cop = new CyclomaticComplexityCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function ok($a) {
    if ($a > 0) {
        return 1;
    }

    return 0;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(0, $offenses);
    }
}
