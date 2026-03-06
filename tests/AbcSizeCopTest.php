<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\AbcSizeCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class AbcSizeCopTest extends TestCase
{
    public function testReportsWhenAbcSizeExceedsLimit(): void
    {
        $cop = new AbcSizeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function process($items) {
    $sum = 0;
    foreach ($items as $item) {
        if ($item > 10 && $item < 100) {
            $sum += transform($item);
        }
    }
    return $sum;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 3]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/AbcSize', $offenses[0]->copName);
        self::assertStringContainsString('process', $offenses[0]->message);
    }

    public function testDoesNotReportWhenWithinLimit(): void
    {
        $cop = new AbcSizeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function tiny() {
    $a = 1;
    return $a;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(0, $offenses);
    }
}
