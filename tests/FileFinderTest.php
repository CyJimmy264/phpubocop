<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Util\FileFinder;
use PHPUnit\Framework\TestCase;

final class FileFinderTest extends TestCase
{
    private static bool $gitAvailable;

    public static function setUpBeforeClass(): void
    {
        self::$gitAvailable = trim((string) shell_exec('command -v git 2>/dev/null')) !== '';
    }

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

    public function testReturnsDiscoveryStats(): void
    {
        $root = sys_get_temp_dir() . '/phpubocop_finder_stats_' . uniqid('', true);
        mkdir($root . '/vendor', 0777, true);

        file_put_contents($root . '/.gitignore', "ignored.php\n");
        file_put_contents($root . '/ignored.php', "<?php\n");
        file_put_contents($root . '/vendor/excluded.php', "<?php\n");
        file_put_contents($root . '/ok.php', "<?php\n");

        $finder = new FileFinder();
        $result = $finder->findWithStats($root, [
            'AllCops' => [
                'Exclude' => ['vendor/**'],
            ],
        ]);

        self::assertSame(2, $result['stats']['php_files_seen']);
        self::assertSame(1, $result['stats']['included']);
        self::assertSame(0, $result['stats']['excluded_by_config']);
        self::assertSame(1, $result['stats']['ignored_by_gitignore']);
    }

    public function testPrunesSimpleGitignoreDirectoryWithoutNegation(): void
    {
        $root = sys_get_temp_dir() . '/phpubocop_finder_prune_' . uniqid('', true);
        mkdir($root . '/ignored/deep', 0777, true);

        file_put_contents($root . '/.gitignore', "ignored/\n");
        file_put_contents($root . '/ignored/deep/a.php', "<?php\n");
        file_put_contents($root . '/ok.php', "<?php\n");

        $finder = new FileFinder();
        $result = $finder->findWithStats($root, [
            'AllCops' => [
                'Exclude' => [],
            ],
        ]);

        $normalized = array_map(static fn (string $f): string => str_replace('\\', '/', $f), $result['files']);

        self::assertContains(str_replace('\\', '/', $root . '/ok.php'), $normalized);
        self::assertNotContains(str_replace('\\', '/', $root . '/ignored/deep/a.php'), $normalized);
        self::assertSame(1, $result['stats']['php_files_seen']);
    }

    public function testUsesGitFileListWhenRepositoryIsAvailable(): void
    {
        if (!self::$gitAvailable) {
            self::markTestSkipped('git is not available in environment');
        }

        $root = sys_get_temp_dir() . '/phpubocop_finder_git_' . uniqid('', true);
        mkdir($root . '/ignored', 0777, true);

        file_put_contents($root . '/.gitignore', "ignored/\n");
        file_put_contents($root . '/ignored/skipped.php', "<?php\n");
        file_put_contents($root . '/ok.php', "<?php\n");

        shell_exec(sprintf('git -C %s init -q', escapeshellarg($root)));

        $finder = new FileFinder();
        $result = $finder->findWithStats($root, [
            'AllCops' => [
                'UseGitFileList' => true,
                'Exclude' => [],
            ],
        ]);

        self::assertSame('git', $result['stats']['source']);
        $normalized = array_map(static fn (string $f): string => str_replace('\\', '/', $f), $result['files']);
        self::assertContains(str_replace('\\', '/', $root . '/ok.php'), $normalized);
        self::assertNotContains(str_replace('\\', '/', $root . '/ignored/skipped.php'), $normalized);
    }

    public function testCanDisableGitFileList(): void
    {
        $root = sys_get_temp_dir() . '/phpubocop_finder_no_git_' . uniqid('', true);
        mkdir($root, 0777, true);
        file_put_contents($root . '/ok.php', "<?php\n");

        $finder = new FileFinder();
        $result = $finder->findWithStats($root, [
            'AllCops' => [
                'UseGitFileList' => false,
                'Exclude' => [],
            ],
        ]);

        self::assertSame('filesystem', $result['stats']['source']);
        self::assertSame(1, $result['stats']['included']);
    }
}
