<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Autocorrector;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class AutocorrectorSafetyTest extends TestCase
{
    public function testAppliesOnlySafeAutocorrectableCops(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_autocorrect_safe_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n\$a = 1;\n");

        $unsafe = new class() implements CopInterface, AutocorrectableCopInterface {
            public function name(): string
            {
                return 'Test/Unsafe';
            }

            public function inspect(SourceFile $file, array $config = []): array
            {
                return [];
            }

            public function autocorrect(SourceFile $file, array $config = []): string
            {
                return str_replace('1', '999', $file->content);
            }
        };

        $safe = new class() implements CopInterface, AutocorrectableCopInterface, SafeAutocorrectableCopInterface {
            public function name(): string
            {
                return 'Test/Safe';
            }

            public function inspect(SourceFile $file, array $config = []): array
            {
                return [];
            }

            public function autocorrect(SourceFile $file, array $config = []): string
            {
                return str_replace('$a', '$b', $file->content);
            }
        };

        $autocorrector = new Autocorrector([$unsafe, $safe]);
        $changed = $autocorrector->run([$file], [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Test/Unsafe' => ['Enabled' => true],
            'Test/Safe' => ['Enabled' => true],
        ]);

        $content = (string) file_get_contents($file);

        self::assertSame(1, $changed);
        self::assertStringContainsString('$b = 1;', $content);
        self::assertStringNotContainsString('999', $content);
    }

    public function testCanApplyUnsafeAutocorrectablesWhenEnabledExplicitly(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_autocorrect_unsafe_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $file = $dir . '/sample.php';
        file_put_contents($file, "<?php\n\$a = 1;\n");

        $unsafe = new class() implements CopInterface, AutocorrectableCopInterface {
            public function name(): string
            {
                return 'Test/Unsafe';
            }

            public function inspect(SourceFile $file, array $config = []): array
            {
                return [];
            }

            public function autocorrect(SourceFile $file, array $config = []): string
            {
                return str_replace('1', '999', $file->content);
            }
        };

        $autocorrector = new Autocorrector([$unsafe]);
        $changed = $autocorrector->run([$file], [
            'AllCops' => ['EnabledByDefault' => true, 'Exclude' => []],
            'Test/Unsafe' => ['Enabled' => true],
        ], true);

        $content = (string) file_get_contents($file);

        self::assertSame(1, $changed);
        self::assertStringContainsString('$a = 999;', $content);
    }
}
