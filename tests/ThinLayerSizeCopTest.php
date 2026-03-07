<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerSizeCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerSizeCopTest extends TestCase
{
    public function testReportsTooLargeThinLayerFile(): void
    {
        $cop = new ThinLayerSizeCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/large.php',
            "<?php\nline1\nline2\nline3\n",
        );

        $offenses = $cop->inspect($source, ['MaxLines' => 3]);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerSize', $offenses[0]->copName);
        self::assertStringContainsString('too large', $offenses[0]->message);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerSizeCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nline1\nline2\nline3\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
            'MaxLines' => 3,
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerSizeCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nline1\nline2\nline3\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
            'MaxLines' => 3,
        ]);

        self::assertCount(0, $offenses);
    }
}
