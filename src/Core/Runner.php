<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Util\FileFinder;

final class Runner
{
    /** @var list<string> */
    private array $lastInspectedFiles = [];

    private array $lastFileStats = [
        'source' => 'filesystem',
        'php_files_seen' => 0,
        'included' => 0,
        'excluded_by_config' => 0,
        'ignored_by_gitignore' => 0,
    ];

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
        $discovery = $this->fileFinder->findWithStats($path, $config);
        $files = $discovery['files'];
        $this->lastInspectedFiles = $files;
        $this->lastFileStats = $discovery['stats'];

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
            static fn (Offense $a, Offense $b): int => [$a->file, $a->line, $a->column, $a->copName] <=> [$b->file, $b->line, $b->column, $b->copName],
        );

        return $offenses;
    }

    public function lastFileStats(): array
    {
        return $this->lastFileStats;
    }

    /** @return list<string> */
    public function lastInspectedFiles(): array
    {
        return $this->lastInspectedFiles;
    }

    private function isCopEnabled(string $copName, array $config, array $copConfig): bool
    {
        if (array_key_exists('Enabled', $copConfig)) {
            return (bool) $copConfig['Enabled'];
        }

        return (bool) ($config['AllCops']['EnabledByDefault'] ?? true);
    }
}
