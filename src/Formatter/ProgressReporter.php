<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class ProgressReporter
{
    private const LINE_WIDTH = 80;

    private int $printed = 0;
    private bool $started = false;

    public function __construct(
        private readonly bool $useColor,
    ) {
    }

    public function start(): void
    {
        $this->started = true;
        echo 'Inspecting files' . PHP_EOL . PHP_EOL;
    }

    /** @param list<Offense> $offenses */
    public function advance(array $offenses): void
    {
        if (!$this->started) {
            return;
        }

        echo $this->progressChar($this->highestSeverity($offenses));
        $this->printed++;

        if ($this->printed % self::LINE_WIDTH === 0) {
            echo PHP_EOL;
        }
    }

    public function finish(): void
    {
        if (!$this->started) {
            return;
        }

        if ($this->printed % self::LINE_WIDTH !== 0 || $this->printed === 0) {
            echo PHP_EOL;
        }

        echo PHP_EOL;
    }

    /** @param list<Offense> $offenses */
    private function highestSeverity(array $offenses): ?string
    {
        $highest = null;
        foreach ($offenses as $offense) {
            $severity = strtolower($offense->severity);
            if ($highest === null || $this->severityRank($severity) > $this->severityRank($highest)) {
                $highest = $severity;
            }
        }

        return $highest;
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

    private function progressChar(?string $severity): string
    {
        if ($severity === null) {
            return $this->paint('.', '0;32');
        }

        [$char, $color] = match ($severity) {
            'fatal' => ['F', '0;31'],
            'error' => ['E', '0;31'],
            'warning' => ['W', '0;35'],
            'refactor' => ['R', '0;36'],
            default => ['C', '0;33'],
        };

        return $this->paint($char, $color);
    }

    private function paint(string $text, string $ansi): string
    {
        if (!$this->useColor) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", $ansi, $text);
    }
}
