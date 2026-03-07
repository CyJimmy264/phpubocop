<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerGlobalStateUsageCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerGlobalStateUsageCopTest extends TestCase
{
    public function testReportsGlobalKeywordUsageInThinLayer(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nglobal \$DB;\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
        self::assertSame('Architecture/ThinLayerGlobalStateUsage', $offenses[0]->copName);
    }

    public function testReportsForbiddenGlobalVariableUsageInThinLayer(): void
    {
        $cop = new ThinLayerGlobalStateUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$id = \$GLOBALS['id'];\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertStringContainsString('$GLOBALS', $offenses[0]->message);
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
        ]);

        self::assertCount(1, $offenses);
    }
}
