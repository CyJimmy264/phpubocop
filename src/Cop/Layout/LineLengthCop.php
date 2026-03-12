<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class LineLengthCop implements CopInterface
{
    public function name(): string
    {
        return 'Layout/LineLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 120);
        $includeInlineHtml = (bool) ($config['IncludeInlineHtml'] ?? false);
        return $this->collectLineLengthOffenses($file, $max, $includeInlineHtml);
    }

    /** @return list<Offense> */
    private function collectLineLengthOffenses(SourceFile $file, int $max, bool $includeInlineHtml): array
    {
        $offenses = [];
        $checkedLines = $this->checkedLines($file, $includeInlineHtml);
        foreach ($file->lines() as $index => $line) {
            if (!($checkedLines[$index + 1] ?? false)) {
                continue;
            }

            $length = mb_strlen($line);
            if ($length > $max) {
                $offenses[] = $this->newOffense($file, $index + 1, $length, $max);
            }
        }

        return $offenses;
    }

    private function newOffense(SourceFile $file, int $line, int $length, int $max): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            $max + 1,
            sprintf('Line is too long. [%d/%d]', $length, $max),
        );
    }

    /** @return array<int,bool> */
    private function checkedLines(SourceFile $file, bool $includeInlineHtml): array
    {
        if ($includeInlineHtml) {
            return $this->allFileLines($file);
        }

        return $this->phpTokenLines($file);
    }

    /** @return array<int,bool> */
    private function allFileLines(SourceFile $file): array
    {
        $lines = [];
        foreach ($file->lines() as $index => $_line) {
            $lines[$index + 1] = true;
        }

        return $lines;
    }

    /** @param array<int,bool> $lines */
    private function markTokenLines(array &$lines, int $line, string $text): void
    {
        $lineCount = substr_count($text, "\n");
        for ($offset = 0; $offset <= $lineCount; $offset++) {
            $lines[$line + $offset] = true;
        }
    }

    /** @return array<int,bool> */
    private function phpTokenLines(SourceFile $file): array
    {
        $lines = [];
        foreach ($file->tokens() as $token) {
            if (!is_array($token) || $this->shouldIgnoreTokenForLineLength($token[0])) {
                continue;
            }

            $this->markTokenLines($lines, (int) $token[2], (string) $token[1]);
        }

        return $lines;
    }

    private function shouldIgnoreTokenForLineLength(int $tokenId): bool
    {
        return in_array($tokenId, [
            T_INLINE_HTML,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
        ], true);
    }
}
