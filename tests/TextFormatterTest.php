<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Core\Offense;
use PHPuboCop\Formatter\TextFormatter;
use PHPUnit\Framework\TestCase;

final class TextFormatterTest extends TestCase
{
    public function testPrintsRelativePathWhenOffenseFileIsInsideCurrentDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_' . uniqid('', true);
        mkdir($dir, 0777, true);
        mkdir($dir . '/sub', 0777, true);
        $file = $dir . '/sub/sample.php';
        file_put_contents($file, "<?php\n");

        $cwd = getcwd();
        self::assertIsString($cwd);

        chdir($dir);
        try {
            putenv('NO_COLOR=1');
            $formatter = new TextFormatter();
            $output = $formatter->format([
                new Offense('Layout/LineLength', $file, 2, 1, 'Line is too long.'),
            ], ['inspected_files' => [$file]]);
            putenv('NO_COLOR');
        } finally {
            chdir($cwd);
        }

        self::assertStringContainsString("sub/sample.php:2:1: C: Layout/LineLength: Line is too long.\n", $output);
    }

    public function testKeepsAbsolutePathWhenOffenseFileIsOutsideCurrentDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_ext_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n");

        putenv('NO_COLOR=1');
        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Layout/LineLength', $file, 2, 1, 'Line is too long.'),
        ], ['inspected_files' => [$file]]);
        putenv('NO_COLOR');

        self::assertStringContainsString($file . ':2:1: C: Layout/LineLength: Line is too long.', $output);
    }

    public function testBuildsProgressSummaryLikeRuboCop(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_progress_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $fileOne = $dir . '/one.php';
        $fileTwo = $dir . '/two.php';
        file_put_contents($fileOne, "<?php\n");
        file_put_contents($fileTwo, "<?php\n");

        putenv('NO_COLOR=1');
        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Layout/LineLength', $fileTwo, 2, 1, 'Line is too long.', 'warning'),
        ], ['inspected_files' => [$fileOne, $fileTwo]]);
        putenv('NO_COLOR');

        self::assertStringContainsString("Inspecting 2 files\n\n.W\n\n", $output);
        self::assertStringContainsString('2 files inspected, 1 offense(s) detected', $output);
    }

    public function testMarksCorrectableOffensesAndPrintsCorrectableSummary(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_correctable_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n");

        putenv('NO_COLOR=1');
        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Style/DoubleQuotes', $file, 2, 1, 'Prefer single-quoted strings.', 'convention', true, true),
        ], ['inspected_files' => [$file]]);
        putenv('NO_COLOR');

        self::assertStringContainsString('C: Style/DoubleQuotes: [Correctable] Prefer single-quoted strings.', $output);
        self::assertStringContainsString('1 files inspected, 1 offense(s) detected, 1 offense(s) autocorrectable', $output);
    }

    public function testPrintsSourceSnippetAndCaretUnderOffense(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_snippet_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n\$value = \"hello\";\n");

        putenv('NO_COLOR=1');
        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Style/DoubleQuotes', $file, 2, 10, 'Prefer single-quoted strings.', 'convention', true, true),
        ], ['inspected_files' => [$file]]);
        putenv('NO_COLOR');

        self::assertStringContainsString('$value = "hello";', $output);
        self::assertStringContainsString("\n         ^\n\n", $output);
    }

    public function testColorsSummaryCountsWhenColorIsEnabled(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_summary_color_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n");

        putenv('NO_COLOR');
        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Style/DoubleQuotes', $file, 2, 1, 'Prefer single-quoted strings.', 'convention', true, true),
        ], ['inspected_files' => [$file]]);

        self::assertStringContainsString('Inspecting 1 files', $output);
        self::assertStringContainsString("\033[0;33m[Correctable]\033[0m Prefer single-quoted strings.", $output);
        self::assertStringContainsString("\033[0;31m1\033[0m offense(s) detected", $output);
        self::assertStringContainsString(", \033[0;33m1\033[0m offense(s) autocorrectable", $output);
    }
}
