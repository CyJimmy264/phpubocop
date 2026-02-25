<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\SuppressedErrorCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class SuppressedErrorCopTest extends TestCase
{
    public function testReportsSuppressedErrorOperator(): void
    {
        $cop = new SuppressedErrorCop();
        $source = new SourceFile('foo.php', "<?php\n\$x = @file_get_contents(\$path);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/SuppressedError', $offenses[0]->copName);
    }

    public function testDoesNotReportWithoutSuppression(): void
    {
        $cop = new SuppressedErrorCop();
        $source = new SourceFile('foo.php', "<?php\n\$x = file_get_contents(\$path);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
