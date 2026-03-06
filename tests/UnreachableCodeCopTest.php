<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\UnreachableCodeCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class UnreachableCodeCopTest extends TestCase
{
    public function testReportsCodeAfterReturnInSameBlock(): void
    {
        $cop = new UnreachableCodeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    return 1;
    $x = 2;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/UnreachableCode', $offenses[0]->copName);
        self::assertSame(4, $offenses[0]->line);
    }

    public function testReportsCodeAfterThrowInBranchBlock(): void
    {
        $cop = new UnreachableCodeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($flag) {
    if ($flag) {
        throw new RuntimeException('x');
        $x = 1;
    }
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame(5, $offenses[0]->line);
    }

    public function testDoesNotReportWhenCodeCanContinueViaIf(): void
    {
        $cop = new UnreachableCodeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($flag) {
    if ($flag) {
        return 1;
    }

    $x = 2;
    return $x;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
