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

        $configLoader = new ConfigLoader();

        $cops = CopRegistry::default();
        $runner = new Runner($cops);
        $offenses = [];
        $inspectedFiles = [];
        foreach ($paths as $path) {
            $resolvedConfig = $this->resolveConfigPathForTarget($path, $configPath);
            $config = $configLoader->load($resolvedConfig['path']);

            if ($autocorrect) {
                (new Autocorrector($cops))->run([$path], $config, $autocorrectAll);
            }

            if ($verbose) {
                $this->printVerboseConfig($path, $resolvedConfig, $config);
            }

            foreach ($runner->run($path, $config) as $offense) {
                $offenses[] = $offense;
            }
            foreach ($runner->lastInspectedFiles() as $filePath) {
                $inspectedFiles[$filePath] = true;
            }

            if ($verbose) {
                $this->printVerboseDiscovery($runner->lastFileStats());
            }
        }

        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int => [$a->file, $a->line, $a->column, $a->copName] <=> [$b->file, $b->line, $b->column, $b->copName]
        );

        $formatter = $this->resolveFormatter($format);
        echo $formatter->format($offenses, ['inspected_files' => array_keys($inspectedFiles)]);

        return $offenses === [] ? 0 : 1;
    }

    private function parseArgs(array $argv): array
    {
        $paths = [];
        $configPath = null;
        $format = 'text';
        $autocorrect = false;
        $autocorrectAll = false;
        $verbose = false;

        for ($i = 1, $count = count($argv); $i < $count; $i++) {
            $arg = $argv[$i];
            if ($arg === '--help' || $arg === '-h') {
                $this->printHelp();
                exit(0);
            }

            if (str_starts_with($arg, '--config=')) {
                $configPath = substr($arg, strlen('--config='));
                continue;
            }

            if ($arg === '--config') {
                $i++;
                $configPath = $argv[$i] ?? null;
                continue;
            }

            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, strlen('--format='));
                continue;
            }

            if ($arg === '--format') {
                $i++;
                $format = $argv[$i] ?? 'text';
                continue;
            }

            if ($arg === '--autocorrect') {
                $autocorrect = true;
                continue;
            }

            if ($arg === '--autocorrect-all') {
                $autocorrect = true;
                $autocorrectAll = true;
                continue;
            }

            if ($arg === '--verbose' || $arg === '-v') {
                $verbose = true;
                continue;
            }

            if (!str_starts_with($arg, '--')) {
                $paths[] = $arg;
            }
        }

        if ($paths === []) {
            $paths[] = '.';
        }

        return [$paths, $configPath, strtolower($format), $autocorrect, $autocorrectAll, $verbose];
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
        $excludeText = $exclude === [] ? '(none)' : implode(', ', array_map(static fn ($item): string => (string) $item, $exclude));

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
            )
        );
    }
}
