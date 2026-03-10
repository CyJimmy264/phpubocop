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

    public function testIgnoresLongInlineHtmlByDefault(): void
    {
        $cop = new LineLengthCop();
        $source = new SourceFile(
            'foo.php',
            '<div>' . str_repeat('x', 130) . "</div>\n<?php\n\$ok = 1;\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 120]);

        self::assertCount(0, $offenses);
    }

    public function testCanIncludeLongInlineHtmlWhenConfigured(): void
    {
        $cop = new LineLengthCop();
        $source = new SourceFile(
            'foo.php',
            '<div>' . str_repeat('x', 130) . "</div>\n<?php\n\$ok = 1;\n",
        );

        $offenses = $cop->inspect($source, [
            'Max' => 120,
            'IncludeInlineHtml' => true,
        ]);

        self::assertCount(1, $offenses);
        self::assertSame(1, $offenses[0]->line);
    }
}
