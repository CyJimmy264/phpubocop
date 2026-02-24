<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class TrailingWhitespaceCop implements CopInterface
{
    public function name(): string
    {
        return 'Layout/TrailingWhitespace';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        foreach ($file->lines() as $index => $line) {
            if (preg_match('/[ \t]+$/', $line, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $column = $matches[0][1] + 1;
            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                $index + 1,
                $column,
                'Trailing whitespace detected.'
            );
        }

        return $offenses;
    }
}
