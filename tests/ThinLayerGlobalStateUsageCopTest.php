<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerGlobalStateUsageCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerGlobalStateUsageCopTest extends TestCase
{
    public function testReportsGlobalKeywordUsageWhenEnabled(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nglobal \$DB;\n",
        );

        $offenses = $cop->inspect($source, [
            'CheckGlobalKeyword' => true,
            'ForbiddenGlobals' => ['DB'],
        ]);

        self::assertCount(2, $offenses);
        self::assertSame('Architecture/ThinLayerGlobalStateUsage', $offenses[0]->copName);
    }

    public function testReportsForbiddenGlobalVariableUsageInThinLayer(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$id = \$DB->query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertStringContainsString('$DB', $offenses[0]->message);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nglobal \$DB;\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nglobal \$DB;\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testCanDisableGlobalKeywordCheck(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nglobal \$DB;\n",
        );

        $offenses = $cop->inspect($source, [
            'CheckGlobalKeyword' => false,
            'ForbiddenGlobals' => ['DB'],
        ]);

        self::assertCount(1, $offenses);
    }
}
