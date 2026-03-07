<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerForbiddenStaticCallsCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerForbiddenStaticCallsCopTest extends TestCase
{
    public function testReportsForbiddenStaticCallInThinLayer(): void
    {
        $cop = new ThinLayerForbiddenStaticCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nBitrix\\Sale\\Order::load(1);\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerForbiddenStaticCalls', $offenses[0]->copName);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerForbiddenStaticCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nBitrix\\Sale\\Order::load(1);\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerForbiddenStaticCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nBitrix\\Sale\\Order::load(1);\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testAcceptsCustomForbiddenStaticClasses(): void
    {
        $cop = new ThinLayerForbiddenStaticCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nApp\\Facade::run();\n",
        );

        $offenses = $cop->inspect($source, [
            'ForbiddenStaticClasses' => ['app\\facade'],
            'ForbiddenStaticCallPrefixes' => [],
        ]);

        self::assertCount(1, $offenses);
    }
}
