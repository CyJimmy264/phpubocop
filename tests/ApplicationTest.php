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
Layout/TrailingCommaInMultiline:
  Enabled: false
Lint/EvalUsage:
  Enabled: false
Lint/SuppressedError:
  Enabled: false
Lint/ShadowingVariable:
  Enabled: false
Lint/UnreachableCode:
  Enabled: false
Lint/UselessAssignment:
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
Style/EmptyCatch:
  Enabled: false
Style/MultilineTernary:
  Enabled: false
Style/BooleanLiteralComparison:
  Enabled: false
Style/StrictComparison:
  Enabled: false
Security/Unserialize:
  Enabled: false
Security/Exec:
  Enabled: false
Security/EvalAndDynamicInclude:
  Enabled: false
YAML,
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

    public function testAutocorrectRewritesFileForAutocorrectableCop(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_autocorrect_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $file = $dir . '/sample.php';
        $config = $dir . '/.phpubocop.yml';

        file_put_contents($file, <<<'PHP'
<?php
$data = [
    'a' => 1,
    'b' => 2
];
PHP,
);

        file_put_contents($config, <<<'YAML'
Layout/LineLength:
  Enabled: false
Layout/TrailingWhitespace:
  Enabled: false
Layout/TrailingCommaInMultiline:
  Enabled: true
Lint/DuplicateArrayKey:
  Enabled: false
Lint/DuplicateMethod:
  Enabled: false
Lint/EvalUsage:
  Enabled: false
Lint/SuppressedError:
  Enabled: false
Lint/ShadowingVariable:
  Enabled: false
Lint/UnreachableCode:
  Enabled: false
Lint/UselessAssignment:
  Enabled: false
Lint/UnusedVariable:
  Enabled: false
Style/DoubleQuotes:
  Enabled: false
Style/EmptyCatch:
  Enabled: false
Style/MultilineTernary:
  Enabled: false
Style/BooleanLiteralComparison:
  Enabled: false
Style/StrictComparison:
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
Security/Unserialize:
  Enabled: false
Security/Exec:
  Enabled: false
Security/EvalAndDynamicInclude:
  Enabled: false
YAML,
);

        $app = new Application();

        ob_start();
        $exitCode = $app->run([
            'phpubocop',
            $file,
            '--config=' . $config,
            '-a',
        ]);
        ob_end_clean();

        $content = (string) file_get_contents($file);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString("'b' => 2,", $content);
    }

    public function testTextOutputPrintsRealtimeProgress(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_progress_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $file = $dir . '/sample.php';
        $config = $dir . '/.phpubocop.yml';

        file_put_contents($file, "<?php\n\$a = \"hello\";\n");
        file_put_contents($config, <<<'YAML'
Layout/LineLength:
  Enabled: false
Layout/TrailingWhitespace:
  Enabled: false
Layout/TrailingCommaInMultiline:
  Enabled: false
Layout/IndentationStyle:
  Enabled: false
Lint/DuplicateArrayKey:
  Enabled: false
Lint/DuplicateMethod:
  Enabled: false
Lint/EvalUsage:
  Enabled: false
Lint/SuppressedError:
  Enabled: false
Lint/ShadowingVariable:
  Enabled: false
Lint/UnreachableCode:
  Enabled: false
Lint/UselessAssignment:
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
Style/EmptyCatch:
  Enabled: false
Style/MultilineTernary:
  Enabled: false
Style/BooleanLiteralComparison:
  Enabled: false
Style/StrictComparison:
  Enabled: false
Security/Unserialize:
  Enabled: false
Security/Exec:
  Enabled: false
Security/EvalAndDynamicInclude:
  Enabled: false
YAML,
);

        putenv('NO_COLOR=1');
        $app = new Application();

        ob_start();
        $exitCode = $app->run([
            'phpubocop',
            $file,
            '--config=' . $config,
        ]);
        $output = (string) ob_get_clean();
        putenv('NO_COLOR');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString("Inspecting files\n\nC\n\n", $output);
    }

    public function testShortAutocorrectFlagsAreParsed(): void
    {
        $app = new Application();
        $method = new \ReflectionMethod($app, 'parseArgs');
        $method->setAccessible(true);

        $safe = $method->invoke($app, ['phpubocop', 'src', '-a']);
        $unsafe = $method->invoke($app, ['phpubocop', 'src', '-A']);

        self::assertSame(['src'], $safe[0]);
        self::assertTrue($safe[4]);
        self::assertFalse($safe[5]);

        self::assertSame(['src'], $unsafe[0]);
        self::assertTrue($unsafe[4]);
        self::assertTrue($unsafe[5]);
    }

    public function testLoadsConfigFromCurrentWorkingDirectoryWhenConfigFlagIsNotProvided(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cwd_cfg_' . uniqid('', true);
        $targetDir = $dir . '/target';
        mkdir($targetDir, 0777, true);

        $file = $targetDir . '/sample.php';
        $cwd = getcwd();
        self::assertIsString($cwd);

        file_put_contents($file, "<?php\n\$a = \"hello\";\n");
        file_put_contents($dir . '/.phpubocop.yml', <<<'YAML'
Style/DoubleQuotes:
  Enabled: false
YAML,
);

        $app = new Application();

        chdir($dir);
        try {
            ob_start();
            $exitCode = $app->run([
                'phpubocop',
                $file,
            ]);
            ob_end_clean();
        } finally {
            chdir($cwd);
        }

        self::assertSame(0, $exitCode);
    }

    public function testPrefersTargetPathConfigOverCurrentWorkingDirectoryConfig(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_priority_' . uniqid('', true);
        $targetDir = $dir . '/target';
        mkdir($targetDir, 0777, true);

        $file = $targetDir . '/sample.php';
        $cwd = getcwd();
        self::assertIsString($cwd);

        file_put_contents($file, "<?php\n\$a = \"hello\";\n");
        file_put_contents($dir . '/.phpubocop.yml', <<<'YAML'
Style/DoubleQuotes:
  Enabled: true
YAML,
);
        file_put_contents($targetDir . '/.phpubocop.yml', <<<'YAML'
Style/DoubleQuotes:
  Enabled: false
YAML,
);

        $app = new Application();

        chdir($dir);
        try {
            ob_start();
            $exitCode = $app->run([
                'phpubocop',
                $file,
            ]);
            ob_end_clean();
        } finally {
            chdir($cwd);
        }

        self::assertSame(0, $exitCode);
    }

    public function testLoadsConfigFromTargetAncestorDirectoryForSingleFileTarget(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_ancestor_' . uniqid('', true);
        $targetDir = $dir . '/www_data/product';
        $cwdDir = $dir . '/cwd';
        mkdir($targetDir, 0777, true);
        mkdir($cwdDir, 0777, true);

        $file = $targetDir . '/sample.php';
        $cwd = getcwd();
        self::assertIsString($cwd);

        file_put_contents($file, <<<'PHP'
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
PHP,
);
        file_put_contents($dir . '/.phpubocop.yml', <<<'YAML'
Security/EvalAndDynamicInclude:
  Enabled: true
  AllowedDynamicIncludePatterns:
    - "\\$_SERVER\\s*\\[\\s*[\"']DOCUMENT_ROOT[\"']\\s*\\]"
YAML,
);
        file_put_contents($cwdDir . '/.phpubocop.yml', <<<'YAML'
Security/EvalAndDynamicInclude:
  Enabled: true
YAML,
);

        $app = new Application();

        chdir($cwdDir);
        try {
            ob_start();
            $exitCode = $app->run([
                'phpubocop',
                $file,
            ]);
            ob_end_clean();
        } finally {
            chdir($cwd);
        }

        self::assertSame(0, $exitCode);
    }
}
