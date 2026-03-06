<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Util\FileFinder;

final class Autocorrector
{
    /** @param list<CopInterface> $cops */
    public function __construct(
        private readonly array $cops,
        private readonly FileFinder $fileFinder = new FileFinder()
    ) {
    }

    /** @param list<string> $paths */
    public function run(array $paths, array $config, bool $includeUnsafe = false): int
    {
        $files = $this->collectFiles($paths, $config);
        $changed = 0;

        foreach ($files as $filePath) {
            $content = (string) file_get_contents($filePath);
            $fixed = $this->autocorrectFileContent($filePath, $content, $config, $includeUnsafe);
            if ($fixed === $content) {
                continue;
            }

            file_put_contents($filePath, $fixed);
            $changed++;
        }

        return $changed;
    }

    private function autocorrectFileContent(
        string $filePath,
        string $content,
        array $config,
        bool $includeUnsafe,
    ): string {
        foreach ($this->cops as $cop) {
            if (!$this->shouldApplyAutocorrectCop($cop, $config, $includeUnsafe)) {
                continue;
            }

            $copConfig = $config[$cop->name()] ?? [];
            $source = new SourceFile($filePath, $content);
            $content = $cop->autocorrect($source, $copConfig);
        }

        return $content;
    }

    private function shouldApplyAutocorrectCop(CopInterface $cop, array $config, bool $includeUnsafe): bool
    {
        if (!$cop instanceof AutocorrectableCopInterface) {
            return false;
        }
        if (!$includeUnsafe && !$cop instanceof SafeAutocorrectableCopInterface) {
            return false;
        }

        $copConfig = $config[$cop->name()] ?? [];
        return $this->isCopEnabled($config, $copConfig);
    }

    /** @param list<string> $paths @return list<string> */
    private function collectFiles(array $paths, array $config): array
    {
        $all = [];
        foreach ($paths as $path) {
            foreach ($this->fileFinder->find($path, $config) as $file) {
                $all[$file] = true;
            }
        }

        $files = array_keys($all);
        sort($files);
        return $files;
    }

    private function isCopEnabled(array $config, array $copConfig): bool
    {
        if (array_key_exists('Enabled', $copConfig)) {
            return (bool) $copConfig['Enabled'];
        }

        return (bool) ($config['AllCops']['EnabledByDefault'] ?? true);
    }
}
