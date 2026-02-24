<?php

declare(strict_types=1);

namespace PHPuboCop;

use PHPuboCop\Config\ConfigLoader;
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
        [$paths, $configPath, $format] = $this->parseArgs($argv);

        $configLoader = new ConfigLoader();
        $config = $configLoader->load($configPath);

        $runner = new Runner(CopRegistry::default());
        $offenses = [];
        foreach ($paths as $path) {
            foreach ($runner->run($path, $config) as $offense) {
                $offenses[] = $offense;
            }
        }

        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int => [$a->file, $a->line, $a->column, $a->copName] <=> [$b->file, $b->line, $b->column, $b->copName]
        );

        $formatter = $this->resolveFormatter($format);
        echo $formatter->format($offenses);

        return $offenses === [] ? 0 : 1;
    }

    private function parseArgs(array $argv): array
    {
        $paths = [];
        $configPath = is_file('.phpubocop.yml') ? '.phpubocop.yml' : null;
        $format = 'text';

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

            if (!str_starts_with($arg, '--')) {
                $paths[] = $arg;
            }
        }

        if ($paths === []) {
            $paths[] = '.';
        }

        return [$paths, $configPath, strtolower($format)];
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
  phpubocop [path ...] [--config=.phpubocop.yml] [--format=text|json]

Examples:
  phpubocop src
  phpubocop src tests
  phpubocop . --format=json

TXT;
    }
}
