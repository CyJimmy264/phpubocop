<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerForbiddenMethodCallsCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerForbiddenMethodCallsCopTest extends TestCase
{
    public function testReportsForbiddenMethodCallInThinLayer(): void
    {
        $cop = new ThinLayerForbiddenMethodCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$db->query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerForbiddenMethodCalls', $offenses[0]->copName);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerForbiddenMethodCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\n\$db->query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerForbiddenMethodCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\n\$db->query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSupportsCustomForbiddenMethodPatterns(): void
    {
        $cop = new ThinLayerForbiddenMethodCallsCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\n\$repo->save(\$data);\n",
        );

        $offenses = $cop->inspect($source, [
            'ForbiddenMethodPatterns' => ['^save$'],
        ]);

        self::assertCount(1, $offenses);
    }
}
