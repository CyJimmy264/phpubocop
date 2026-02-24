<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Util\FileFinder;
use PHPUnit\Framework\TestCase;

final class FileFinderTest extends TestCase
{
    public function testRespectsGitignoreRules(): void
    {
        $root = sys_get_temp_dir() . '/phpubocop_finder_' . uniqid('', true);
        mkdir($root . '/sub', 0777, true);

        file_put_contents($root . '/.gitignore', "ignored.php\nsub/\n!sub/keep.php\n");
        file_put_contents($root . '/ignored.php', "<?php\n");
        file_put_contents($root . '/visible.php', "<?php\n");
        file_put_contents($root . '/sub/skip.php', "<?php\n");
        file_put_contents($root . '/sub/keep.php', "<?php\n");

        $finder = new FileFinder();
        $files = $finder->find($root, [
            'AllCops' => [
                'Exclude' => [],
            ],
        ]);

        $normalized = array_map(static fn (string $f): string => str_replace('\\', '/', $f), $files);

        self::assertContains(str_replace('\\', '/', $root . '/visible.php'), $normalized);
        self::assertContains(str_replace('\\', '/', $root . '/sub/keep.php'), $normalized);
        self::assertNotContains(str_replace('\\', '/', $root . '/ignored.php'), $normalized);
        self::assertNotContains(str_replace('\\', '/', $root . '/sub/skip.php'), $normalized);
    }
}
