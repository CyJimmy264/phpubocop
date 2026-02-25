<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Metrics\MethodLengthCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class MethodLengthCopTest extends TestCase
{
    public function testReportsMethodLongerThanConfiguredMax(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $a = 1;
    $b = 2;
    $c = 3;
    return $a + $b + $c;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(1, $offenses);
        self::assertSame('Metrics/MethodLength', $offenses[0]->copName);
    }

    public function testDoesNotReportShortMethod(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function tiny() {
    return 1;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 5]);

        self::assertCount(0, $offenses);
    }

    public function testCountAsOneArrayReducesEffectiveLength(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $data = [
        'a' => 1,
        'b' => 2,
    ];
    return $data;
}
PHP
);

        $without = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => []]);
        $with = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => ['array']]);

        self::assertCount(1, $without);
        self::assertCount(0, $with);
    }

    public function testCountAsOneHashAliasAlsoReducesArrayLength(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $data = [
        'a' => 1,
        'b' => 2,
    ];
    return $data;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => ['hash']]);

        self::assertCount(0, $offenses);
    }

    public function testCountAsOneHeredocReducesEffectiveLength(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo() {
    $text = <<<TXT
line1
line2
TXT;
    return $text;
}
PHP
);

        $without = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => []]);
        $with = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => ['heredoc']]);

        self::assertCount(1, $without);
        self::assertCount(0, $with);
    }

    public function testCountAsOneCallChainReducesChainedCallLength(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($service) {
    $result = $service
        ->stepOne()
        ->stepTwo()
        ->stepThree();
    return $result;
}
PHP
);

        $without = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => []]);
        $with = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => ['call_chain']]);

        self::assertCount(1, $without);
        self::assertCount(0, $with);
    }

    public function testLegacyMethodCallAliasStillWorks(): void
    {
        $cop = new MethodLengthCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
function demo($service) {
    $result = $service
        ->stepOne()
        ->stepTwo()
        ->stepThree();
    return $result;
}
PHP
);

        $offenses = $cop->inspect($source, ['Max' => 6, 'CountAsOne' => ['method_call']]);

        self::assertCount(0, $offenses);
    }
}
