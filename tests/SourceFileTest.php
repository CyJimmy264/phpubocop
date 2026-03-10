<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class SourceFileTest extends TestCase
{
    public function testLinesDoNotSplitOnUnicodeSeparatorsInsideContent(): void
    {
        $source = new SourceFile(
            'foo.php',
            "<?php\n\$message = \"first\u{2028}second\";\nreturn;\n",
        );

        self::assertSame(
            [
                '<?php',
                '$message = "first' . "\u{2028}" . 'second";',
                'return;',
                '',
            ],
            $source->lines(),
        );
    }
}
