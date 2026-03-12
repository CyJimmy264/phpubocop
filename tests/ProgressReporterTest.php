<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Core\Offense;
use PHPuboCop\Formatter\ProgressReporter;
use PHPUnit\Framework\TestCase;

final class ProgressReporterTest extends TestCase
{
    public function testPrintsRealtimeProgressSequence(): void
    {
        $reporter = new ProgressReporter(false);

        ob_start();
        $reporter->start();
        $reporter->advance([]);
        $reporter->advance([
            new Offense('Style/DoubleQuotes', 'a.php', 1, 1, 'x', 'convention'),
        ]);
        $reporter->advance([
            new Offense('Security/Exec', 'b.php', 1, 1, 'x', 'warning'),
        ]);
        $reporter->finish();
        $output = (string) ob_get_clean();

        self::assertSame("Inspecting files\n\n.CW\n\n", $output);
    }
}
