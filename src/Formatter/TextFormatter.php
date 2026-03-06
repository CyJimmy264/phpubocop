<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class TextFormatter implements FormatterInterface
{
    public function format(array $offenses): string
    {
        if ($offenses === []) {
            return "No offenses detected.\n";
        }

        $cwd = getcwd();
        $normalizedCwd = is_string($cwd) ? $this->normalizePath($cwd) : null;

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
                $offense->copName
            );
        }

        $lines[] = sprintf('%d offense(s) detected.', count($offenses));
        return implode(PHP_EOL, $lines) . PHP_EOL;
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
}
