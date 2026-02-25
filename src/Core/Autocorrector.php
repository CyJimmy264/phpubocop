<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
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
    public function run(array $paths, array $config): int
    {
        $files = $this->collectFiles($paths, $config);
        $changed = 0;

        foreach ($files as $filePath) {
            $content = (string) file_get_contents($filePath);
            $original = $content;

            foreach ($this->cops as $cop) {
                if (!$cop instanceof AutocorrectableCopInterface) {
                    continue;
                }

                $copConfig = $config[$cop->name()] ?? [];
                if (!$this->isCopEnabled($config, $copConfig)) {
                    continue;
                }

                $source = new SourceFile($filePath, $content);
                $content = $cop->autocorrect($source, $copConfig);
            }

            if ($content !== $original) {
                file_put_contents($filePath, $content);
                $changed++;
            }
        }

        return $changed;
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
