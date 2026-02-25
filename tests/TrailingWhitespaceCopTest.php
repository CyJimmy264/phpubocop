<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Layout\TrailingWhitespaceCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class TrailingWhitespaceCopTest extends TestCase
{
    public function testDetectsTrailingWhitespace(): void
    {
        $cop = new TrailingWhitespaceCop();
        $source = new SourceFile('foo.php', "<?php\n\$a = 1;   \n\$b = 2;\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Layout/TrailingWhitespace', $offenses[0]->copName);
        self::assertSame(2, $offenses[0]->line);
    }

    public function testAutocorrectRemovesTrailingWhitespace(): void
    {
        $cop = new TrailingWhitespaceCop();
        $source = new SourceFile('foo.php', "<?php\n\$a = 1;   \n\$b = 2;\t\n");

        $fixed = $cop->autocorrect($source);

        self::assertSame("<?php\n\$a = 1;\n\$b = 2;\n", $fixed);
    }
}
