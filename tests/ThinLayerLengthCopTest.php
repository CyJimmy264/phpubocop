<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerLengthCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerLengthCopTest extends TestCase
{
    public function testReportsTooLargeThinLayerFile(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/large.php',
            "<?php\n\$a = 1;\n\$b = 2;\n\$c = 3;\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 2]);

        self::assertCount(1, $offenses);
        self::assertSame('Architecture/ThinLayerLength', $offenses[0]->copName);
        self::assertStringContainsString('too large', $offenses[0]->message);
    }

    public function testSkipsBusinessLayerFilesByConfiguredPaths(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/lib/service.php',
            "<?php\nline1\nline2\nline3\n",
        );

        $offenses = $cop->inspect($source, [
            'BusinessLayerPaths' => ['www_data/local/php_interface/lib/**'],
            'Max' => 3,
        ]);

        self::assertCount(0, $offenses);
    }

    public function testSkipsExcludedPaths(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/local/php_interface/migrations/20260307_init.php',
            "<?php\nline1\nline2\nline3\n",
        );

        $offenses = $cop->inspect($source, [
            'ExcludePaths' => ['www_data/local/php_interface/migrations/**'],
            'Max' => 3,
        ]);

        self::assertCount(0, $offenses);
    }

    public function testCountsOnlyPhpCodeLinesInMixedPhpHtmlFile(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/include/offer_content.php',
            "<div>block</div>\n<?php\n\$x = 1;\n?>\n<footer>end</footer>\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 1]);

        self::assertCount(0, $offenses);
    }

    public function testCountsMultilineArrayAsOneByDefault(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/payload.php',
            "<?php\n\$payload = [\n    'a',\n    'b',\n    'c',\n];\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 2]);

        self::assertCount(0, $offenses);
    }

    public function testCanDisableArrayCountAsOne(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/payload.php',
            "<?php\n\$payload = [\n    'a',\n    'b',\n    'c',\n];\n",
        );

        $offenses = $cop->inspect($source, [
            'Max' => 2,
            'CountAsOne' => [],
        ]);

        self::assertCount(1, $offenses);
    }

    public function testCountsHeredocAsOneByDefault(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/text.php',
            "<?php\n\$text = <<<TXT\nline1\nline2\nTXT;\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 2]);

        self::assertCount(0, $offenses);
    }

    public function testCountsCallChainAsOneByDefault(): void
    {
        $cop = new ThinLayerLengthCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/chain.php',
            "<?php\n\$result = \$builder\n    ->stepOne()\n    ->stepTwo()\n    ->stepThree();\n",
        );

        $offenses = $cop->inspect($source, ['Max' => 2]);

        self::assertCount(0, $offenses);
    }
}
