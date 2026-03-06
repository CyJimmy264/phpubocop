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
            $formatter = new TextFormatter();
            $output = $formatter->format([
                new Offense('Layout/LineLength', $file, 2, 1, 'Line is too long.'),
            ], ['inspected_files' => [$file]]);
        } finally {
            chdir($cwd);
        }

        self::assertStringContainsString("sub/sample.php:2:1: convention: Line is too long. (Layout/LineLength)\n", $output);
    }

    public function testKeepsAbsolutePathWhenOffenseFileIsOutsideCurrentDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_formatter_ext_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n");

        $formatter = new TextFormatter();
        $output = $formatter->format([
            new Offense('Layout/LineLength', $file, 2, 1, 'Line is too long.'),
        ], ['inspected_files' => [$file]]);

        self::assertStringContainsString($file . ':2:1: convention: Line is too long. (Layout/LineLength)', $output);
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

        self::assertStringContainsString(".W\n\n", $output);
        self::assertStringContainsString('2 files inspected, 1 offense(s) detected in 1 file(s)', $output);
    }
}
