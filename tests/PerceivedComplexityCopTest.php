<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\PerceivedComplexityCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class PerceivedComplexityCopTest extends TestCase
{
    public function testReportsWhenPerceivedComplexityExceedsLimit(): void
    {
        $cop = new PerceivedComplexityCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function evaluate($a, $b, $c) {
    if ($a > 0) {
        switch ($b) {
            case 1:
                return 1;
            case 2:
                return 2;
            default:
                return 3;
        }
    } else {
        if ($c > 0 && $b > 0) {
            return 4;
        }
    }

    return 0;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/PerceivedComplexity', $offenses[0]->copName);
        self::assertStringContainsString('evaluate', $offenses[0]->message);
    }

    public function testDoesNotReportWhenWithinLimit(): void
    {
        $cop = new PerceivedComplexityCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function tiny($a) {
    if ($a > 0) {
        return 1;
    }

    return 0;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(0, $offenses);
    }
}
