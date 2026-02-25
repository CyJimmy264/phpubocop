<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\MethodLengthCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class MethodLengthCopTest extends TestCase
{
    public function testReportsMethodLongerThanConfiguredMax(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $a = 1;
    $b = 2;
    $c = 3;
    return $a + $b + $c;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/MethodLength', $offenses[0]->copName);
    }

    public function testDoesNotReportShortMethod(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function tiny() {
    return 1;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(0, $offenses);
    }
}
