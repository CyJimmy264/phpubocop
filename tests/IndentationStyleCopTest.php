<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Layout\IndentationStyleCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class IndentationStyleCopTest extends TestCase
{
    public function testDetectsTabIndentation(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n\t\$a = 1;\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Layout/IndentationStyle', $offenses[0]->copName);
        self::assertSame(2, $offenses[0]->line);
        self::assertSame(1, $offenses[0]->column);
    }

    public function testDetectsMixedIndentationWithTab(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n  \t\$a = 1;\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame(2, $offenses[0]->line);
        self::assertSame(3, $offenses[0]->column);
    }

    public function testDoesNotReportTabOutsideLeadingIndentation(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n    echo \"\\t\";\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testCanBeDisabledByStyleConfig(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n\t\$a = 1;\n");

        $offenses = $cop->inspect($source, ['Style' => 'tabs']);

        self::assertCount(0, $offenses);
    }

    public function testAutocorrectReplacesLeadingTabsWithSpaces(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n\t\$a = 1;\n");

        $fixed = $cop->autocorrect($source);

        self::assertSame("<?php\n    \$a = 1;\n", $fixed);
    }

    public function testAutocorrectUsesConfiguredTabWidth(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', "<?php\n\t\$a = 1;\n");

        $fixed = $cop->autocorrect($source, ['TabWidth' => 2]);

        self::assertSame("<?php\n  \$a = 1;\n", $fixed);
    }

    public function testAutocorrectDoesNotChangeHeredocContentIndentation(): void
    {
        $cop = new IndentationStyleCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$s = <<<TXT
	inside
TXT;
PHP
);

        $fixed = $cop->autocorrect($source);
        $offenses = $cop->inspect($source);

        self::assertSame($source->content, $fixed);
        self::assertCount(0, $offenses);
    }
}
