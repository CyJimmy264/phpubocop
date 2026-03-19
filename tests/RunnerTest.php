<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Layout\LineLengthCop;
use PHPuboCop\Cop\Lint\EvalUsageCop;
use PHPuboCop\Cop\Style\DoubleQuotesCop;
use PHPuboCop\Cop\Style\StrictComparisonCop;
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

    public function testRunnerSuppressesOffenseViaLineCommentDirective(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_disable_line_' . uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sample.php', <<<'PHP'
<?php
// phpubocop:disable Style/DoubleQuotes
$value = "hello";
PHP,
);

        $runner = new Runner([new DoubleQuotesCop()]);
        $config = [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Style/DoubleQuotes' => ['Enabled' => true],
        ];

        $offenses = $runner->run($dir, $config);

        self::assertCount(0, $offenses);
    }

    public function testRunnerSuppressesOffenseViaBlockCommentDirective(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_disable_block_' . uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sample.php', <<<'PHP'
<?php
/* phpubocop:disable Layout/LineLength */
$value = "aaaaaaaaaaa";
PHP,
);

        $runner = new Runner([new LineLengthCop()]);
        $config = [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Layout/LineLength' => ['Enabled' => true, 'Max' => 10],
        ];

        $offenses = $runner->run($dir, $config);

        self::assertCount(0, $offenses);
    }

    public function testRunnerSupportsMultipleCopNamesAndLeavesOtherOffenses(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_disable_multi_' . uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sample.php', <<<'PHP'
<?php
// phpubocop:disable Style/DoubleQuotes
eval($code); $value = "hello";
PHP,
);

        $runner = new Runner([new DoubleQuotesCop(), new EvalUsageCop()]);
        $config = [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Style/DoubleQuotes' => ['Enabled' => true],
            'Lint/EvalUsage' => ['Enabled' => true],
        ];

        $offenses = $runner->run($dir, $config);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/EvalUsage', $offenses[0]->copName);
    }

    public function testRunnerPreservesPerOffenseAutocorrectMetadata(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_strict_metadata_' . uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/sample.php', <<<'PHP'
<?php
if ($a == 'Y') {
    return;
}
PHP,
);

        $runner = new Runner([new StrictComparisonCop()]);
        $config = [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Style/StrictComparison' => ['Enabled' => true],
        ];

        $offenses = $runner->run($dir, $config);

        self::assertCount(1, $offenses);
        self::assertFalse($offenses[0]->correctable);
        self::assertFalse($offenses[0]->safeAutocorrect);
    }
}
