<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class TextFormatter implements FormatterInterface
{
    private const PROGRESS_LINE_WIDTH = 80;

    public function format(array $offenses, array $context = []): string
    {
        $inspectedFiles = $this->inspectedFiles($context);
        $useColor = getenv('NO_COLOR') === false;
        $parts = [
            $this->buildProgressPart($inspectedFiles, $offenses, $useColor),
            $this->formatOffenses($offenses, $this->normalizedCwd()),
            $this->buildSummary(
            count($inspectedFiles),
            count($offenses),
            $this->countOffendingFiles($offenses),
            $useColor,
            ),
        ];

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

    private function normalizedCwd(): ?string
    {
        $cwd = getcwd();
        return is_string($cwd) ? $this->normalizePath($cwd) : null;
    }

    /** @param array{inspected_files?:list<mixed>} $context */
    private function inspectedFiles(array $context): array
    {
        $rawFiles = $context['inspected_files'] ?? [];
        return array_values(
            array_unique(array_map(static fn ($value): string => (string) $value, $rawFiles)),
        );
    }

    /** @param list<Offense> $offenses */
    private function formatOffenses(array $offenses, ?string $normalizedCwd): string
    {
        if ($offenses === []) {
            return '';
        }

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

        return implode(PHP_EOL, $lines);
    }

    /** @param list<Offense> $offenses */
    private function countOffendingFiles(array $offenses): int
    {
        return count(array_unique(array_map(static fn (Offense $offense): string => $offense->file, $offenses)));
    }

    /** @param list<string> $inspectedFiles @param list<Offense> $offenses */
    private function buildProgressPart(array $inspectedFiles, array $offenses, bool $useColor): string
    {
        if ($inspectedFiles === []) {
            return '';
        }

        return $this->buildProgressLine($inspectedFiles, $offenses, $useColor);
    }

    /** @param list<string> $inspectedFiles @param list<Offense> $offenses */
    private function buildProgressLine(array $inspectedFiles, array $offenses, bool $useColor): string
    {
        $byFile = $this->highestSeverityByFile($offenses);

        $chars = [];
        foreach ($inspectedFiles as $inspectedFile) {
            $severity = $byFile[$inspectedFile] ?? null;
            $chars[] = $this->progressChar($severity, $useColor);
        }

        return implode(PHP_EOL, $this->wrapProgressChars($chars));
    }

    private function buildSummary(int $fileCount, int $offenseCount, int $offendingFileCount, bool $useColor): string
    {
        if ($offenseCount === 0) {
            return sprintf(
                '%d files inspected, %s',
                $fileCount,
                $this->paint('no offenses detected', '0;32', $useColor),
            );
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

    /** @param list<Offense> $offenses @return array<string,string> */
    private function highestSeverityByFile(array $offenses): array
    {
        $byFile = [];
        foreach ($offenses as $offense) {
            $offenseFile = $offense->file;
            $severity = strtolower($offense->severity);

            if (!isset($byFile[$offenseFile])) {
                $byFile[$offenseFile] = $severity;
                continue;
            }

            if ($this->severityRank($severity) > $this->severityRank($byFile[$offenseFile])) {
                $byFile[$offenseFile] = $severity;
            }
        }

        return $byFile;
    }

    private function progressChar(?string $severity, bool $useColor): string
    {
        if ($severity === null) {
            return $this->paint('.', '0;32', $useColor);
        }

        [$char, $color] = match ($severity) {
            'fatal' => ['F', '0;31'],
            'error' => ['E', '0;31'],
            'warning' => ['W', '0;35'],
            'refactor' => ['R', '0;36'],
            default => ['C', '0;33'],
        };

        return $this->paint($char, $color, $useColor);
    }

    /** @param list<string> $chars @return list<string> */
    private function wrapProgressChars(array $chars): array
    {
        $wrapped = [];
        $current = '';
        foreach ($chars as $char) {
            $current .= $char;
            if ($this->visibleLength($current) >= self::PROGRESS_LINE_WIDTH) {
                $wrapped[] = $current;
                $current = '';
            }
        }

        if ($current !== '') {
            $wrapped[] = $current;
        }

        return $wrapped;
    }

    private function visibleLength(string $text): int
    {
        $withoutAnsi = preg_replace('/\e\[[\d;]*m/', '', $text);
        return strlen($withoutAnsi ?? $text);
    }
}
