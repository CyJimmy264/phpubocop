<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Layout\LineLengthCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class LineLengthCopTest extends TestCase
{
    public function testDetectsLongLine(): void
    {
        $cop = new LineLengthCop();
        $source = new SourceFile('foo.php', "<?php\n" . str_repeat('a', 11) . "\n");

        $offenses = $cop->inspect($source, ['Max' => 10]);

        self::assertCount(1, $offenses);
        self::assertSame('Layout/LineLength', $offenses[0]->copName);
    }
}
