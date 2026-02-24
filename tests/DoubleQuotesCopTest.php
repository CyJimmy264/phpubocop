<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Style\DoubleQuotesCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class DoubleQuotesCopTest extends TestCase
{
    public function testDetectsSimpleDoubleQuotedString(): void
    {
        $cop = new DoubleQuotesCop();
        $source = new SourceFile('foo.php', "<?php\n\$a = \"hello\";\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Style/DoubleQuotes', $offenses[0]->copName);
    }

    public function testSkipsDoubleQuotedStringWithEscapes(): void
    {
        $cop = new DoubleQuotesCop();
        $source = new SourceFile('foo.php', "<?php\n\$a = \"\\n\";\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
