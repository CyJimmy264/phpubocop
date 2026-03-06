<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Security\EvalAndDynamicIncludeCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class EvalAndDynamicIncludeCopTest extends TestCase
{
    public function testReportsEvalUsage(): void
    {
        $cop = new EvalAndDynamicIncludeCop();
        $source = new SourceFile('foo.php', "<?php\neval(\$code);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Security/EvalAndDynamicInclude', $offenses[0]->copName);
    }

    public function testReportsDynamicIncludePath(): void
    {
        $cop = new EvalAndDynamicIncludeCop();
        $source = new SourceFile('foo.php', "<?php\ninclude \$file;\nrequire_once \$path;\n");

        $offenses = $cop->inspect($source);

        self::assertCount(2, $offenses);
    }

    public function testDoesNotReportStaticIncludePath(): void
    {
        $cop = new EvalAndDynamicIncludeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
include __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/app.php';
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testAllowsDynamicIncludeByConfiguredPattern(): void
    {
        $cop = new EvalAndDynamicIncludeCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
PHP,
);

        $offenses = $cop->inspect($source, [
            'AllowedDynamicIncludePatterns' => [
                '\$_SERVER\s*\[\s*[\"\']DOCUMENT_ROOT[\"\']\s*\]',
            ],
        ]);

        self::assertCount(0, $offenses);
    }
}
