<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

final class SourcePosition
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }
}
