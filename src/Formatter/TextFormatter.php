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

        $lines = [];
        foreach ($offenses as $offense) {
            $lines[] = sprintf(
                '%s:%d:%d: %s: %s (%s)',
                $offense->file,
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
}
