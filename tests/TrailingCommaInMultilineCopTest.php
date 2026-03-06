<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Layout\TrailingCommaInMultilineCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class TrailingCommaInMultilineCopTest extends TestCase
{
    public function testReportsMissingTrailingCommaInMultilineArray(): void
    {
        $cop = new TrailingCommaInMultilineCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$data = [
    'a' => 1,
    'b' => 2
];
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Layout/TrailingCommaInMultiline', $offenses[0]->copName);
    }

    public function testReportsMissingTrailingCommaInMultilineCall(): void
    {
        $cop = new TrailingCommaInMultilineCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
run(
    1,
    2
);
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
    }

    public function testAutocorrectAddsTrailingComma(): void
    {
        $cop = new TrailingCommaInMultilineCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$data = [
    'a' => 1,
    'b' => 2
];
PHP,
);

        $fixed = $cop->autocorrect($source);

        self::assertStringContainsString("'b' => 2,", $fixed);
    }

    public function testDoesNotReportWhenTrailingCommaExists(): void
    {
        $cop = new TrailingCommaInMultilineCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$data = [
    'a' => 1,
    'b' => 2,
];
PHP,
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testSkipsWhenClosingParenIsOnSameLineAsLastArg(): void
    {
        $cop = new TrailingCommaInMultilineCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
$result = $query->where([
    'a' => 1,
    'b' => 2,
])->fetch();
PHP,
);

        $offenses = $cop->inspect($source);
        $fixed = $cop->autocorrect($source);

        self::assertCount(0, $offenses);
        self::assertSame($source->content, $fixed);
    }
}
