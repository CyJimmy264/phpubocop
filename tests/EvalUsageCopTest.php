<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\EvalUsageCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class EvalUsageCopTest extends TestCase
{
    public function testDetectsEvalCall(): void
    {
        $cop = new EvalUsageCop();
        $source = new SourceFile('foo.php', '<?php' . "\n" . 'eval(\'$x = 1;\');' . "\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/EvalUsage', $offenses[0]->copName);
        self::assertSame(2, $offenses[0]->line);
    }
}
