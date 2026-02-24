<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

final class Offense
{
    public function __construct(
        public readonly string $copName,
        public readonly string $file,
        public readonly int $line,
        public readonly int $column,
        public readonly string $message,
        public readonly string $severity = 'convention'
    ) {
    }

    public function toArray(): array
    {
        return [
            'cop' => $this->copName,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }
}
