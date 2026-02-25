<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Lint\UnusedVariableCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class UnusedVariableCopTest extends TestCase
{
    public function testReportsUnusedAssignedVariable(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $unused = 1;
    return 42;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Lint/UnusedVariable', $offenses[0]->copName);
        self::assertStringContainsString('$unused', $offenses[0]->message);
    }

    public function testDoesNotReportUsedVariable(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $used = 1;
    return $used;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testIgnoresUnderscorePrefixedVariableByDefault(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $_meta = 1;
    return 42;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testCanReportUnusedParameterWhenConfigured(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($param) {
    return 42;
}
PHP
);

        $offenses = $cop->inspect($source, ['IgnoreParameters' => false]);

        self::assertCount(1, $offenses);
        self::assertStringContainsString('$param', $offenses[0]->message);
    }

    public function testTreatsCompactAsVariableUsage(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $title = 'Hi';
    return compact('title');
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testSkipsScopeWithExtractCallToAvoidFalsePositives(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(array $payload) {
    $tmp = 1;
    extract($payload);
    return 42;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testSkipsScopeWithParseStrWithoutTargetArray(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(string $query) {
    $tmp = 1;
    parse_str($query);
    return 42;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }

    public function testDoesNotSkipScopeWithParseStrIntoTargetArray(): void
    {
        $cop = new UnusedVariableCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo(string $query) {
    $tmp = 1;
    parse_str($query, $out);
    return $out;
}
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertStringContainsString('$tmp', $offenses[0]->message);
    }
}
