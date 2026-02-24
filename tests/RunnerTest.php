<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Core\CopRegistry;
use PHPuboCop\Core\Runner;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    public function testRunnerFindsOffensesInDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_' . uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sample.php', "<?php\n\$a = \"hello\";\n");

        $runner = new Runner(CopRegistry::default());
        $config = [
            'AllCops' => [
                'EnabledByDefault' => true,
                'Exclude' => [],
            ],
            'Style/DoubleQuotes' => ['Enabled' => true],
        ];

        $offenses = $runner->run($dir, $config);

        self::assertNotEmpty($offenses);
    }
}
