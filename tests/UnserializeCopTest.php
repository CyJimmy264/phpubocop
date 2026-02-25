<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Security\UnserializeCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class UnserializeCopTest extends TestCase
{
    public function testReportsUnserializeWithoutOptions(): void
    {
        $cop = new UnserializeCop();
        $source = new SourceFile('foo.php', "<?php\n\$x = unserialize(\$payload);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
        self::assertSame('Security/Unserialize', $offenses[0]->copName);
    }

    public function testReportsUnserializeWithNonStrictAllowedClasses(): void
    {
        $cop = new UnserializeCop();
        $source = new SourceFile('foo.php', "<?php\n\$x = unserialize(\$payload, ['allowed_classes' => true]);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(1, $offenses);
    }

    public function testAllowsUnserializeWithAllowedClassesFalse(): void
    {
        $cop = new UnserializeCop();
        $source = new SourceFile('foo.php', "<?php\n\$x = unserialize(\$payload, ['allowed_classes' => false]);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
