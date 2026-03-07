<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerForbiddenFunctionsCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerForbiddenFunctionsCopTest extends TestCase
{
    public function testReportsForbiddenFunctionInThinLayer(): void
    {
        $cop = new ThinLayerForbiddenFunctionsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/order/index.php',
            "<?php\nmysql_query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerForbiddenFunctions', $offenses[0]->copName);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerForbiddenFunctionsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nmysql_query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerForbiddenFunctionsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nmysql_query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testAcceptsCustomForbiddenFunctions(): void
    {
        $cop = new ThinLayerForbiddenFunctionsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\ncustom_call();\n",
        );

        $offenses = $cop->inspect($source, [
            'ForbiddenFunctions' => ['custom_call'],
        ]);

        self::assertCount(1, $offenses);
    }
}
