<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\UselessAssignmentCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class UselessAssignmentCopTest extends TestCase
{
    public function testReportsOverwrittenAssignmentBeforeRead(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $x = 1;
    $x = 2;
    return $x;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/UselessAssignment', $offenses[0]->copName);
        self::assertSame(3, $offenses[0]->line);
    }

    public function testDoesNotReportWhenValueIsReadBeforeOverwrite(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $x = 1;
    echo $x;
    $x = 2;
    return $x;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testReportsMultipleUselessAssignments(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $x = 1;
    $x = 2;
    $x = 3;
    return $x;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
        self::assertSame(3, $offenses[0]->line);
        self::assertSame(4, $offenses[1]->line);
    }
}
