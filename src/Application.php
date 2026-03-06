<?php

declare(strict_types=1);

namespace PHPuboCop;

use PHPuboCop\Config\ConfigLoader;
use PHPuboCop\Core\Autocorrector;
use PHPuboCop\Core\CopRegistry;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\Runner;
use PHPuboCop\Formatter\FormatterInterface;
use PHPuboCop\Formatter\JsonFormatter;
use PHPuboCop\Formatter\TextFormatter;

final class Application
{
    public function run(array $argv): int
    {
        [$paths, $configPath, $format, $autocorrect, $autocorrectAll, $verbose] = $this->parseArgs($argv);

        $cops = CopRegistry::default();
        $configLoader = new ConfigLoader();
        $runner = new Runner($cops);
        $offenses = [];
        $inspectedFiles = [];
        $runContext = [
            'configPath' => $configPath,
            'autocorrect' => $autocorrect,
            'autocorrectAll' => $autocorrectAll,
            'verbose' => $verbose,
            'cops' => $cops,
            'configLoader' => $configLoader,
            'runner' => $runner,
        ];

        foreach ($paths as $path) {
            $this->runForPath($path, $runContext, $offenses, $inspectedFiles);
        }

        return $this->finalizeRun($offenses, $inspectedFiles, $format);
    }

    /**
     * @param array{
     *   configPath:?string,
     *   autocorrect:bool,
     *   autocorrectAll:bool,
     *   verbose:bool,
     *   cops:list<object>,
     *   configLoader:ConfigLoader,
     *   runner:Runner
     * } $context
     * @param list<Offense> $offenses
     * @param array<string,bool> $inspectedFiles
     */
    private function runForPath(
        string $path,
        array $context,
        array &$offenses,
        array &$inspectedFiles,
    ): void {
        $resolvedConfig = $this->resolveConfigPathForTarget($path, $context['configPath']);
        $config = $context['configLoader']->load($resolvedConfig['path']);

        if ($context['autocorrect']) {
            (new Autocorrector($context['cops']))->run([$path], $config, $context['autocorrectAll']);
        }

        if ($context['verbose']) {
            $this->printVerboseConfig($path, $resolvedConfig, $config);
        }

        foreach ($context['runner']->run($path, $config) as $offense) {
            $offenses[] = $offense;
        }

        $this->collectInspectedFiles($context['runner']->lastInspectedFiles(), $inspectedFiles);

        if ($context['verbose']) {
            $this->printVerboseDiscovery($context['runner']->lastFileStats());
        }
    }

    /** @param list<string> $files @param array<string,bool> $inspectedFiles */
    private function collectInspectedFiles(array $files, array &$inspectedFiles): void
    {
        foreach ($files as $filePath) {
            $inspectedFiles[$filePath] = true;
        }
    }

    /** @param list<Offense> $offenses @param array<string,bool> $inspectedFiles */
    private function finalizeRun(array $offenses, array $inspectedFiles, string $format): int
    {
        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int => [$a->file, $a->line, $a->column, $a->copName] <=> [$b->file, $b->line, $b->column, $b->copName],
        );

        $formatter = $this->resolveFormatter($format);
        echo $formatter->format(
            $offenses,
            ['inspected_files' => array_keys($inspectedFiles)],
        );

        return $offenses === [] ? 0 : 1;
    }

    private function parseArgs(array $argv): array
    {
        $state = [
            'paths' => [],
            'configPath' => null,
            'format' => 'text',
            'autocorrect' => false,
            'autocorrectAll' => false,
            'verbose' => false,
        ];

        for ($i = 1, $count = count($argv); $i < $count; $i++) {
            $arg = (string) $argv[$i];
            if ($this->isHelpArg($arg)) {
                $this->printHelp();
                exit(0);
            }

            $this->consumeArg($arg, $argv, $i, $state);
        }

        if ($state['paths'] === []) {
            $state['paths'][] = '.';
        }

        return [
            $state['paths'],
            $state['configPath'],
            strtolower((string) $state['format']),
            (bool) $state['autocorrect'],
            (bool) $state['autocorrectAll'],
            (bool) $state['verbose'],
        ];
    }

    private function isHelpArg(string $arg): bool
    {
        return $arg === '--help' || $arg === '-h';
    }

    /** @param array{paths:list<string>,configPath:?string,format:string,autocorrect:bool,autocorrectAll:bool,verbose:bool} $state */
    private function consumeArg(string $arg, array $argv, int &$i, array &$state): void
    {
        if ($this->consumeConfigArg($arg, $argv, $i, $state)) {
            return;
        }
        if ($this->consumeFormatArg($arg, $argv, $i, $state)) {
            return;
        }
        if ($this->consumeToggleArg($arg, $state)) {
            return;
        }

        if (!str_starts_with($arg, '--')) {
            $state['paths'][] = $arg;
        }
    }

    /** @param array{configPath:?string} $state */
    private function consumeConfigArg(string $arg, array $argv, int &$i, array &$state): bool
    {
        if (str_starts_with($arg, '--config=')) {
            $state['configPath'] = substr($arg, strlen('--config='));
            return true;
        }
        if ($arg !== '--config') {
            return false;
        }

        $i++;
        $state['configPath'] = $argv[$i] ?? null;
        return true;
    }

    /** @param array{format:string} $state */
    private function consumeFormatArg(string $arg, array $argv, int &$i, array &$state): bool
    {
        if (str_starts_with($arg, '--format=')) {
            $state['format'] = substr($arg, strlen('--format='));
            return true;
        }
        if ($arg !== '--format') {
            return false;
        }

        $i++;
        $state['format'] = $argv[$i] ?? 'text';
        return true;
    }

    /** @param array{autocorrect:bool,autocorrectAll:bool,verbose:bool} $state */
    private function consumeToggleArg(string $arg, array &$state): bool
    {
        if ($arg === '--autocorrect') {
            $state['autocorrect'] = true;
            return true;
        }
        if ($arg === '--autocorrect-all') {
            $state['autocorrect'] = true;
            $state['autocorrectAll'] = true;
            return true;
        }
        if ($arg === '--verbose' || $arg === '-v') {
            $state['verbose'] = true;
            return true;
        }

        return false;
    }

    /** @return array{path:?string,source:string} */
    private function resolveConfigPathForTarget(string $targetPath, ?string $explicitConfigPath): array
    {
        if ($explicitConfigPath !== null) {
            return ['path' => $explicitConfigPath, 'source' => 'explicit'];
        }

        if (is_dir($targetPath)) {
            $candidate = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.phpubocop.yml';
            if (is_file($candidate)) {
                return ['path' => $candidate, 'source' => 'target_directory'];
            }
        }

        if (is_file($targetPath)) {
            $candidate = dirname($targetPath) . DIRECTORY_SEPARATOR . '.phpubocop.yml';
            if (is_file($candidate)) {
                return ['path' => $candidate, 'source' => 'target_file_directory'];
            }
        }

        if (is_file('.phpubocop.yml')) {
            return ['path' => '.phpubocop.yml', 'source' => 'current_working_directory'];
        }

        return ['path' => null, 'source' => 'defaults'];
    }

    private function resolveFormatter(string $format): FormatterInterface
    {
        return match ($format) {
            'json' => new JsonFormatter(),
            default => new TextFormatter(),
        };
    }

    private function printHelp(): void
    {
        echo <<<TXT
PHPuboCop - RuboCop-inspired linter for PHP

Usage:
  phpubocop [path ...] [--config=.phpubocop.yml] [--format=text|json] [--autocorrect] [--autocorrect-all] [--verbose]

Examples:
  phpubocop src
  phpubocop src tests
  phpubocop . --format=json
  phpubocop src --autocorrect
  phpubocop src --autocorrect-all
  phpubocop src --verbose

TXT;
    }

    private function printVerboseConfig(string $path, array $resolvedConfig, array $config): void
    {
        $configPath = $resolvedConfig['path'] ?? '<defaults>';
        $exclude = $config['AllCops']['Exclude'] ?? [];
        $excludeText = $exclude === []
            ? '(none)'
            : implode(', ', array_map(static fn ($item): string => (string) $item, $exclude));

        fwrite(STDERR, sprintf("[phpubocop] target: %s\n", $path));
        fwrite(STDERR, sprintf("[phpubocop] config: %s (%s)\n", $configPath, $resolvedConfig['source']));
        fwrite(STDERR, sprintf("[phpubocop] excludes: %s\n", $excludeText));
    }

    private function printVerboseDiscovery(array $stats): void
    {
        $source = (string) ($stats['source'] ?? 'filesystem');
        fwrite(
            STDERR,
            sprintf(
                "[phpubocop] files(%s): php_seen=%d, included=%d, excluded_by_config=%d, ignored_by_gitignore=%d\n",
                $source,
                (int) ($stats['php_files_seen'] ?? 0),
                (int) ($stats['included'] ?? 0),
                (int) ($stats['excluded_by_config'] ?? 0),
                (int) ($stats['ignored_by_gitignore'] ?? 0),
            ),
        );
    }
}
