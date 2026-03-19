<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
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
    public function run(string $path, array $config, ?callable $afterFile = null): array
    {
        $discovery = $this->fileFinder->findWithStats($path, $config);
        $files = $discovery['files'];
        $this->lastInspectedFiles = $files;
        $this->lastFileStats = $discovery['stats'];

        return $this->sortedOffenses($this->collectOffensesForFiles($files, $config, $afterFile));
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

    /** @param list<string> $files @return list<Offense> */
    private function collectOffensesForFiles(array $files, array $config, ?callable $afterFile): array
    {
        $offenses = [];
        foreach ($files as $filePath) {
            $fileOffenses = $this->inspectFile($filePath, $config);
            foreach ($fileOffenses as $offense) {
                $offenses[] = $offense;
            }

            if ($afterFile !== null) {
                $afterFile($filePath, $fileOffenses);
            }
        }

        return $offenses;
    }

    /** @return list<Offense> */
    private function inspectFile(string $filePath, array $config): array
    {
        $sourceFile = new SourceFile($filePath, (string) file_get_contents($filePath));
        $suppressionMap = new InlineSuppressionMap($sourceFile);
        $offenses = [];
        foreach ($this->cops as $cop) {
            $copConfig = $config[$cop->name()] ?? [];
            if (!$this->isCopEnabled($cop->name(), $config, $copConfig)) {
                continue;
            }

            $this->appendCopOffenses($offenses, $cop, $sourceFile, $copConfig, $suppressionMap);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function appendCopOffenses(
        array &$offenses,
        CopInterface $cop,
        SourceFile $sourceFile,
        array $copConfig,
        InlineSuppressionMap $suppressionMap,
    ): void {
        $correctable = $cop instanceof AutocorrectableCopInterface;
        $safeAutocorrect = $cop instanceof SafeAutocorrectableCopInterface;
        foreach ($cop->inspect($sourceFile, $copConfig) as $offense) {
            $annotated = $offense->hasExplicitAutocorrectMetadata()
                ? $offense
                : $offense->withAutocorrect($correctable, $safeAutocorrect);
            if ($suppressionMap->suppresses($annotated)) {
                continue;
            }

            $offenses[] = $annotated;
        }
    }

    /** @param list<Offense> $offenses @return list<Offense> */
    private function sortedOffenses(array $offenses): array
    {
        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int =>
                [$a->file, $a->line, $a->column, $a->copName]
                <=>
                [$b->file, $b->line, $b->column, $b->copName],
        );

        return $offenses;
    }
}
