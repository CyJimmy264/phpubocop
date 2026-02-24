<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Util\FileFinder;

final class Runner
{
    /** @param list<CopInterface> $cops */
    public function __construct(
        private readonly array $cops,
        private readonly FileFinder $fileFinder = new FileFinder()
    ) {
    }

    /** @return list<Offense> */
    public function run(string $path, array $config): array
    {
        $offenses = [];
        $files = $this->fileFinder->find($path, $config);

        foreach ($files as $filePath) {
            $content = (string) file_get_contents($filePath);
            $sourceFile = new SourceFile($filePath, $content);

            foreach ($this->cops as $cop) {
                $copConfig = $config[$cop->name()] ?? [];
                if (!$this->isCopEnabled($cop->name(), $config, $copConfig)) {
                    continue;
                }

                foreach ($cop->inspect($sourceFile, $copConfig) as $offense) {
                    $offenses[] = $offense;
                }
            }
        }

        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int => [$a->file, $a->line, $a->column, $a->copName] <=> [$b->file, $b->line, $b->column, $b->copName]
        );

        return $offenses;
    }

    private function isCopEnabled(string $copName, array $config, array $copConfig): bool
    {
        if (array_key_exists('Enabled', $copConfig)) {
            return (bool) $copConfig['Enabled'];
        }

        return (bool) ($config['AllCops']['EnabledByDefault'] ?? true);
    }
}
