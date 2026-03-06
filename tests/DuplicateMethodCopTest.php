<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\DuplicateMethodCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class DuplicateMethodCopTest extends TestCase
{
    public function testReportsDuplicateMethodsInClass(): void
    {
        $cop = new DuplicateMethodCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
class A {
    public function run() {}
    public function run() {}
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/DuplicateMethod', $offenses[0]->copName);
    }

    public function testReportsDuplicateFunctionsInSameNamespace(): void
    {
        $cop = new DuplicateMethodCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
namespace Demo;

function f() {}
function f() {}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
    }

    public function testDoesNotReportDistinctDeclarations(): void
    {
        $cop = new DuplicateMethodCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
namespace Demo;

function f() {}
function g() {}

class A {
    public function run() {}
    public function stop() {}
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
