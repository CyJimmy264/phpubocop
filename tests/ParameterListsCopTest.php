<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\ParameterListsCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ParameterListsCopTest extends TestCase
{
    public function testReportsTooManyParametersInFunction(): void
    {
        $cop = new ParameterListsCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($a, $b, $c, $d, $e, $f) {
    return $a;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/ParameterLists', $offenses[0]->copName);
    }

    public function testReportsTooManyParametersInArrowFunction(): void
    {
        $cop = new ParameterListsCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$fn = fn($a, $b, $c) => $a + $b + $c;
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 2]);

        self::assertCount(1, $offenses);
    }

    public function testDoesNotReportWhenWithinLimit(): void
    {
        $cop = new ParameterListsCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function tiny($a, $b) {
    return $a + $b;
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 3]);

        self::assertCount(0, $offenses);
    }

    public function testReportsTooManyConstructorParameters(): void
    {
        $cop = new ParameterListsCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
class Demo {
    public function __construct($a, $b, $c, $d, $e, $f) {}
}
PHP,
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(1, $offenses);
    }
}
