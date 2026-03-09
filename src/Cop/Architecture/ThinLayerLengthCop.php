<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class ThinLayerLengthCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $maxLines = (int) ($config['Max'] ?? 50);
        $lineCount = $this->lineCount($file->content);
        if ($lineCount <= $maxLines) {
            return [];
        }

        return [
            new Offense(
                $this->name(),
                $file->path,
                1,
                1,
                sprintf('Thin-layer script is too large. Lines [%d/%d].', $lineCount, $maxLines),
                'warning',
            ),
        ];
    }

    private function lineCount(string $contents): int
    {
        if ($contents === '') {
            return 0;
        }

        $tokens = token_get_all($contents);
        return $this->countSignificantLines($tokens);
    }

    /** @param list<string|array{int,string,int}> $tokens */
    private function countSignificantLines(array $tokens): int
    {
        $significantLines = [];
        $currentLine = 1;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $currentLine = $this->handleArrayToken($token, $significantLines);
                continue;
            }

            $currentLine = $this->handleStringToken($token, $currentLine, $significantLines);
        }

        return count($significantLines);
    }

    /** @param array{int,string,int} $token @param array<int, bool> $significantLines */
    private function handleArrayToken(array $token, array &$significantLines): int
    {
        [$tokenId, $text, $line] = $token;
        $line = (int) $line;
        $text = (string) $text;

        if ($this->isSignificantToken((int) $tokenId)) {
            $this->markLines($significantLines, $line, $text);
        }

        return $line + substr_count($text, "\n");
    }

    /** @param array<int, bool> $significantLines */
    private function handleStringToken(string $token, int $currentLine, array &$significantLines): int
    {
        if (trim($token) !== '') {
            $significantLines[$currentLine] = true;
        }

        return $currentLine + substr_count($token, "\n");
    }

    private function isSignificantToken(int $tokenId): bool
    {
        return !in_array($tokenId, [
            T_INLINE_HTML,
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
        ], true);
    }

    /** @param array<int, bool> $lines */
    private function markLines(array &$lines, int $startLine, string $text): void
    {
        $lineCount = substr_count($text, "\n");
        for ($offset = 0; $offset <= $lineCount; $offset++) {
            $lines[$startLine + $offset] = true;
        }
    }
}
