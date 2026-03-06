<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Style\EmptyCatchCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class EmptyCatchCopTest extends TestCase
{
    public function testReportsEmptyCatchBlock(): void
    {
        $cop = new EmptyCatchCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
try {
    risky();
} catch (RuntimeException $e) {
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Style/EmptyCatch', $offenses[0]->copName);
    }

    public function testDoesNotReportNonEmptyCatchBlock(): void
    {
        $cop = new EmptyCatchCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
try {
    risky();
} catch (RuntimeException $e) {
    error_log($e->getMessage());
}
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
