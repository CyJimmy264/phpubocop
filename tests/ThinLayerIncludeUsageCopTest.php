<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerIncludeUsageCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerIncludeUsageCopTest extends TestCase
{
    public function testReportsDisallowedIncludeInThinLayer(): void
    {
        $cop = new ThinLayerIncludeUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nrequire(\$_SERVER['DOCUMENT_ROOT'] . '/custom/bootstrap.php');\n",
        );

        $offenses = $cop->inspect($source, [
            'AllowedIncludePatterns' => ['/bitrix/header.php'],
        ]);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerIncludeUsage', $offenses[0]->copName);
    }

    public function testAllowsIncludeByConfiguredPattern(): void
    {
        $cop = new ThinLayerIncludeUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nrequire(\$_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');\n",
        );

        $offenses = $cop->inspect($source, [
            'AllowedIncludePatterns' => ['/bitrix/header.php'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerIncludeUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nrequire('x.php');\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
            'AllowedIncludePatterns' => [],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerIncludeUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nrequire('x.php');\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
            'AllowedIncludePatterns' => [],
        ]);

        self::assertCount(0, $offenses);
    }
}
