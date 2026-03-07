<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerSuperglobalUsageCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerSuperglobalUsageCopTest extends TestCase
{
    public function testReportsForbiddenSuperglobalUsageInThinLayer(): void
    {
        $cop = new ThinLayerSuperglobalUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$id = \$_REQUEST['id'];\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerSuperglobalUsage', $offenses[0]->copName);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerSuperglobalUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\n\$id = \$_REQUEST['id'];\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerSuperglobalUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\n\$id = \$_REQUEST['id'];\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSupportsCustomForbiddenSuperglobals(): void
    {
        $cop = new ThinLayerSuperglobalUsageCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$id = \$_GET['id'];\n",
        );

        $offenses = $cop->inspect($source, [
            'ForbiddenSuperglobals' => ['_GET'],
        ]);

        self::assertCount(1, $offenses);
    }
}
