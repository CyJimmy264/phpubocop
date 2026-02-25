<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testSupportsMultiplePaths(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_app_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $fileOne = $dir . '/one.php';
        $fileTwo = $dir . '/two.php';
        $config = $dir . '/.phpubocop.yml';

        file_put_contents($fileOne, "<?php\n\$a = \"hello\";\n");
        file_put_contents($fileTwo, "<?php\n\$b = \"world\";\n");
        file_put_contents($config, <<<'YAML'
Layout/LineLength:
  Enabled: false
Layout/TrailingWhitespace:
  Enabled: false
Lint/EvalUsage:
  Enabled: false
Lint/UnreachableCode:
  Enabled: false
Lint/UnusedVariable:
  Enabled: false
Metrics/AbcSize:
  Enabled: false
Metrics/CyclomaticComplexity:
  Enabled: false
Metrics/MethodLength:
  Enabled: false
Metrics/PerceivedComplexity:
  Enabled: false
Metrics/ParameterLists:
  Enabled: false
Style/DoubleQuotes:
  Enabled: true
Security/Unserialize:
  Enabled: false
YAML
);

        $app = new Application();

        ob_start();
        $exitCode = $app->run([
            'phpubocop',
            $fileOne,
            $fileTwo,
            '--config=' . $config,
            '--format=json',
        ]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);

        self::assertSame(1, $exitCode);
        self::assertIsArray($decoded);
        self::assertCount(2, $decoded['offenses']);
    }
}
