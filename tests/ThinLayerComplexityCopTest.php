<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerComplexityCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerComplexityCopTest extends TestCase
{
    public function testReportsExcessiveBranchComplexityInThinLayer(): void
    {
        $cop = new ThinLayerComplexityCop();
        $source = new SourceFile('/tmp/project/www_data/ajax/complex.php', <<<'PHP'
<?php
if ($a) {}
if ($b) {}
if ($c) {}
PHP,
);

        $offenses = $cop->inspect($source, ['MaxBranchNodes' => 2]);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerComplexity', $offenses[0]->copName);
        self::assertStringContainsString('Thin-layer script is too complex', $offenses[0]->message);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerComplexityCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nif (\$a) {}\nif (\$b) {}\nif (\$c) {}\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
            'MaxBranchNodes' => 2,
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerComplexityCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nif (\$a) {}\nif (\$b) {}\nif (\$c) {}\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
            'MaxBranchNodes' => 2,
        ]);

        self::assertCount(0, $offenses);
    }
}
