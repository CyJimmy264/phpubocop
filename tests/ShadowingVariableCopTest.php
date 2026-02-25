<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\ShadowingVariableCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ShadowingVariableCopTest extends TestCase
{
    public function testReportsForeachVariableShadowing(): void
    {
        $cop = new ShadowingVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(array $items) {
    $item = 'x';
    foreach ($items as $item) {
        echo $item;
    }
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/ShadowingVariable', $offenses[0]->copName);
        self::assertSame(4, $offenses[0]->line);
    }

    public function testReportsCatchVariableShadowing(): void
    {
        $cop = new ShadowingVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $e = null;
    try {
        risky();
    } catch (RuntimeException $e) {
        echo $e->getMessage();
    }
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame(6, $offenses[0]->line);
    }

    public function testDoesNotReportFreshForeachVariable(): void
    {
        $cop = new ShadowingVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(array $items) {
    foreach ($items as $item) {
        echo $item;
    }
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
