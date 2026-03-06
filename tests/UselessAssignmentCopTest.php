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
PHP,
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
PHP,
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
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
        self::assertSame(3, $offenses[0]->line);
        self::assertSame(4, $offenses[1]->line);
    }

    public function testDoesNotReportDefaultAssignmentOverwrittenOnlyInsideConditionalBranch(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(bool $recalculate): float {
    $discountAbs = 0.0;

    if ($recalculate) {
        $discountAbs = 5.0;
    }

    return $discountAbs;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testDoesNotReportWhenOverwriteExpressionReadsPreviousValue(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(): array {
    $ids = [];
    $ids = array_values(array_unique($ids));
    return $ids;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testDoesNotReportWhenTryAndCatchAssignmentsAreMutuallyExclusive(): void
    {
        $cop = new UselessAssignmentCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(): array {
    $savedCoupons = [];

    try {
        $saved = getCoupons();
        if (is_array($saved)) {
            $savedCoupons = array_keys($saved);
        }
    } catch (\Throwable $e) {
        $savedCoupons = [];
    }

    return $savedCoupons;
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
