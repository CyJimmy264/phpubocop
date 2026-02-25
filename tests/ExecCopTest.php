<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Security\ExecCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ExecCopTest extends TestCase
{
    public function testReportsDangerousExecFunctions(): void
    {
        $cop = new ExecCop();
        $source = new SourceFile('foo.php', <<<'PHP'
<?php
exec($cmd);
shell_exec($cmd);
proc_open($cmd, [], $pipes);
PHP
);

        $offenses = $cop->inspect($source);

        self::assertCount(3, $offenses);
        self::assertSame('Security/Exec', $offenses[0]->copName);
    }

    public function testDoesNotReportSafeFunctionCalls(): void
    {
        $cop = new ExecCop();
        $source = new SourceFile('foo.php', "<?php\ntrim(\$value);\n");

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
