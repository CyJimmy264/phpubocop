<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\DuplicateArrayKeyCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class DuplicateArrayKeyCopTest extends TestCase
{
    public function testReportsDuplicateStringKey(): void
    {
        $cop = new DuplicateArrayKeyCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$data = [
    'id' => 1,
    'id' => 2,
];
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/DuplicateArrayKey', $offenses[0]->copName);
        self::assertStringContainsString("'id'", $offenses[0]->message);
    }

    public function testDoesNotReportDistinctKeys(): void
    {
        $cop = new DuplicateArrayKeyCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$data = [
    'id' => 1,
    'name' => 2,
    1 => 3,
    2 => 4,
];
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testIgnoresDynamicKeys(): void
    {
        $cop = new DuplicateArrayKeyCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$key = 'id';
$data = [
    $key => 1,
    $key => 2,
];
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
