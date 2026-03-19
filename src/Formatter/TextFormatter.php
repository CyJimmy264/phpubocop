<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class TextFormatter implements FormatterInterface
{
    private const TAB_WIDTH = 8;

    public function format(array $offenses, array $context = []): string
    {
        $inspectedFiles = $this->inspectedFiles($context);
        $useColor = getenv('NO_COLOR') === false;
        $parts = [
            $this->formatOffenses($offenses, $this->normalizedCwd(), $useColor),
            $this->buildSummary(
                count($inspectedFiles),
                count($offenses),
                $this->countCorrectableOffenses($offenses),
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
    private function formatOffenses(array $offenses, ?string $normalizedCwd, bool $useColor): string
    {
        if ($offenses === []) {
            return '';
        }

        $blocks = [];
        foreach ($offenses as $offense) {
            $displayPath = $this->relativePathFromCwd($offense->file, $normalizedCwd);
            $blocks[] = $this->formatOffenseBlock($offense, $displayPath, $useColor);
        }

        return implode(PHP_EOL, $blocks);
    }

    /** @param list<Offense> $offenses */
    private function countCorrectableOffenses(array $offenses): int
    {
        return count(array_filter($offenses, static fn (Offense $offense): bool => $offense->correctable));
    }

    private function formatOffenseBlock(Offense $offense, string $displayPath, bool $useColor): string
    {
        $parts = [
            $this->formatOffenseHeadline($offense, $displayPath, $useColor),
        ];

        $snippet = $this->formatOffenseSnippet($offense, $useColor);
        if ($snippet !== null) {
            $parts[] = $snippet;
        }

        return implode(PHP_EOL, $parts);
    }

    private function formatOffenseHeadline(Offense $offense, string $displayPath, bool $useColor): string
    {
        $message = $offense->message;
        if ($offense->correctable) {
            $message = $this->paint('[Correctable]', '0;33', $useColor) . ' ' . $message;
        }

        return sprintf(
            '%s:%d:%d: %s: %s: %s',
            $this->paint($displayPath, '0;36', $useColor),
            $offense->line,
            $offense->column,
            $this->severityLetter($offense->severity, $useColor),
            $offense->copName,
            $message,
        );
    }

    private function formatOffenseSnippet(Offense $offense, bool $useColor): ?string
    {
        $sourceLine = $this->readSourceLine($offense->file, $offense->line);
        if ($sourceLine === null) {
            return null;
        }

        $trimmed = rtrim($sourceLine, "\r\n");
        $displayLine = $this->expandTabs($trimmed);
        $caretLine = $this->caretLine($trimmed, $offense->column);

        return sprintf(
            '%s%s%s',
            $displayLine,
            PHP_EOL,
            $this->paint($caretLine, '0;33', $useColor),
        );
    }

    private function readSourceLine(string $file, int $line): ?string
    {
        if (!is_readable($file)) {
            return null;
        }

        $lines = file($file);
        if (!is_array($lines) || !isset($lines[$line - 1])) {
            return null;
        }

        return (string) $lines[$line - 1];
    }

    private function caretLine(string $sourceLine, int $column): string
    {
        $prefixLength = max(0, $column - 1);
        $prefix = substr($sourceLine, 0, $prefixLength);

        return str_repeat(' ', $this->visualWidth($prefix ?: '')) . '^';
    }

    private function expandTabs(string $text): string
    {
        $expanded = '';
        $column = 0;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char !== "\t") {
                $expanded .= $char;
                $column++;
                continue;
            }

            $spaces = self::TAB_WIDTH - ($column % self::TAB_WIDTH);
            $expanded .= str_repeat(' ', $spaces);
            $column += $spaces;
        }

        return $expanded;
    }

    private function visualWidth(string $text): int
    {
        return strlen($this->expandTabs($text));
    }

    private function buildSummary(
        int $fileCount,
        int $offenseCount,
        int $correctableCount,
        bool $useColor,
    ): string
    {
        if ($offenseCount === 0) {
            return sprintf(
                '%d files inspected, %s',
                $fileCount,
                $this->paint('no offenses detected', '0;32', $useColor),
            );
        }

        $summary = sprintf(
            '%d files inspected, %s offense(s) detected',
            $fileCount,
            $this->paint((string) $offenseCount, '0;31', $useColor),
        );

        if ($correctableCount > 0) {
            $summary .= sprintf(
                ', %s offense(s) autocorrectable',
                $this->paint((string) $correctableCount, '0;33', $useColor),
            );
        }

        return $summary;
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

    private function severityLetter(string $severity, bool $useColor): string
    {
        return match (strtolower($severity)) {
            'fatal' => $this->paint('F', '0;31', $useColor),
            'error' => $this->paint('E', '0;31', $useColor),
            'warning' => $this->paint('W', '0;35', $useColor),
            'refactor' => $this->paint('R', '0;36', $useColor),
            default => $this->paint('C', '0;33', $useColor),
        };
    }
}
