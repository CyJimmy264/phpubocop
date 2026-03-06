<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class TextFormatter implements FormatterInterface
{
    public function format(array $offenses, array $context = []): string
    {
        $inspectedFiles = array_values(array_unique(array_map(static fn ($v): string => (string) $v, $context['inspected_files'] ?? [])));
        $useColor = getenv('NO_COLOR') === false;
        $cwd = getcwd();
        $normalizedCwd = is_string($cwd) ? $this->normalizePath($cwd) : null;
        $parts = [];

        if ($inspectedFiles !== []) {
            $parts[] = $this->buildProgressLine($inspectedFiles, $offenses, $useColor);
        }

        if ($offenses !== []) {
            $lines = [];
            foreach ($offenses as $offense) {
                $displayPath = $this->relativePathFromCwd($offense->file, $normalizedCwd);
                $lines[] = sprintf(
                    '%s:%d:%d: %s: %s (%s)',
                    $displayPath,
                    $offense->line,
                    $offense->column,
                    $offense->severity,
                    $offense->message,
                    $offense->copName,
                );
            }
            $parts[] = implode(PHP_EOL, $lines);
        }

        $parts[] = $this->buildSummary(count($inspectedFiles), count($offenses), count(array_unique(array_map(static fn ($o): string => $o->file, $offenses))), $useColor);

        return implode(PHP_EOL . PHP_EOL, array_filter($parts, static fn (string $p): bool => $p !== '')) . PHP_EOL;
    }

    private function relativePathFromCwd(string $path, ?string $normalizedCwd): string
    {
        if ($normalizedCwd === null) {
            return $path;
        }

        $normalizedPath = $this->normalizePath($path);
        if (!$this->isAbsolutePath($normalizedPath)) {
            return $path;
        }

        $cwdPrefix = rtrim($normalizedCwd, '/') . '/';
        if (!str_starts_with($normalizedPath, $cwdPrefix)) {
            return $path;
        }

        return substr($normalizedPath, strlen($cwdPrefix));
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }

    /** @param list<string> $inspectedFiles @param list<\PHPuboCop\Core\Offense> $offenses */
    private function buildProgressLine(array $inspectedFiles, array $offenses, bool $useColor): string
    {
        $byFile = [];
        foreach ($offenses as $offense) {
            $file = $offense->file;
            $severity = strtolower($offense->severity);
            if (!isset($byFile[$file]) || $this->severityRank($severity) > $this->severityRank($byFile[$file])) {
                $byFile[$file] = $severity;
            }
        }

        $chars = [];
        foreach ($inspectedFiles as $file) {
            $severity = $byFile[$file] ?? null;
            if ($severity === null) {
                $chars[] = $this->paint('.', '0;32', $useColor);
                continue;
            }

            [$char, $color] = match ($severity) {
                'fatal' => ['F', '0;31'],
                'error' => ['E', '0;31'],
                'warning' => ['W', '0;35'],
                'refactor' => ['R', '0;36'],
                default => ['C', '0;33'],
            };
            $chars[] = $this->paint($char, $color, $useColor);
        }

        $wrapped = [];
        $current = '';
        foreach ($chars as $char) {
            $current .= $char;
            if (strlen(preg_replace('/\e\[[\d;]*m/', '', $current) ?? $current) >= 80) {
                $wrapped[] = $current;
                $current = '';
            }
        }
        if ($current !== '') {
            $wrapped[] = $current;
        }

        return implode(PHP_EOL, $wrapped);
    }

    private function buildSummary(int $fileCount, int $offenseCount, int $offendingFileCount, bool $useColor): string
    {
        if ($offenseCount === 0) {
            return sprintf('%d files inspected, %s', $fileCount, $this->paint('no offenses detected', '0;32', $useColor));
        }

        return sprintf(
            '%d files inspected, %d offense(s) detected in %d file(s)',
            $fileCount,
            $offenseCount,
            $offendingFileCount,
        );
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'fatal' => 5,
            'error' => 4,
            'warning' => 3,
            'refactor' => 2,
            default => 1,
        };
    }

    private function paint(string $text, string $ansi, bool $useColor): string
    {
        if (!$useColor) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", $ansi, $text);
    }
}
