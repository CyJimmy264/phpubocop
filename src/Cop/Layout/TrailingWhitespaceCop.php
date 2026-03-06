<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class TrailingWhitespaceCop implements CopInterface, AutocorrectableCopInterface, SafeAutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Layout/TrailingWhitespace';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        return $this->collectTrailingWhitespaceOffenses($file);
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        return preg_replace('/[ \t]+$/m', '', $file->content) ?? $file->content;
    }

    /** @return list<Offense> */
    private function collectTrailingWhitespaceOffenses(SourceFile $file): array
    {
        $offenses = [];
        foreach ($file->lines() as $index => $line) {
            $column = $this->trailingWhitespaceColumn($line);
            if ($column !== null) {
                $offenses[] = $this->newOffense($file, $index + 1, $column);
            }
        }

        return $offenses;
    }

    private function trailingWhitespaceColumn(string $line): ?int
    {
        if (preg_match('/[ \t]+$/', $line, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $matches[0][1] + 1;
    }

    private function newOffense(SourceFile $file, int $line, int $column): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            $column,
            'Trailing whitespace detected.',
        );
    }
}
