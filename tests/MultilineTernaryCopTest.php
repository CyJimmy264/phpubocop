<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Style\MultilineTernaryCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class MultilineTernaryCopTest extends TestCase
{
    public function testReportsMultilineTernary(): void
    {
        $cop = new MultilineTernaryCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$value = $flag
    ? 'yes'
    : 'no';
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Style/MultilineTernary', $offenses[0]->copName);
    }

    public function testDoesNotReportSingleLineTernary(): void
    {
        $cop = new MultilineTernaryCop();
        $source = new SourceFile('foo.php', "<?php\n\$value = \$flag ? 'yes' : 'no';\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
