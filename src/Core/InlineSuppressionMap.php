<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

final class InlineSuppressionMap
{
    /** @var array<int,array<string,bool>> */
    private array $disabledByLine = [];

    public function __construct(SourceFile $file)
    {
        $significantLines = $this->significantCodeLines($file->content);
        foreach (token_get_all($file->content) as $token) {
            if (!is_array($token) || !$this->isCommentToken($token[0])) {
                continue;
            }

            $this->registerDirective($token, $significantLines);
        }
    }

    public function suppresses(Offense $offense): bool
    {
        $disabled = $this->disabledByLine[$offense->line] ?? null;
        if ($disabled === null) {
            return false;
        }

        return ($disabled['all'] ?? false) || ($disabled[strtolower($offense->copName)] ?? false);
    }

    /** @return array<int,bool> */
    private function significantCodeLines(string $content): array
    {
        $lines = [];
        foreach (token_get_all($content) as $token) {
            if (!is_array($token) || $this->ignoredSignificantToken($token[0])) {
                continue;
            }

            $line = (int) $token[2];
            $lineCount = substr_count((string) $token[1], "\n");
            for ($offset = 0; $offset <= $lineCount; $offset++) {
                $lines[$line + $offset] = true;
            }
        }

        return $lines;
    }

    /** @param array{0:int,1:string,2:int} $token @param array<int,bool> $significantLines */
    private function registerDirective(array $token, array $significantLines): void
    {
        $copNames = $this->directiveCopNames((string) $token[1]);
        if ($copNames === []) {
            return;
        }

        $startLine = (int) $token[2];
        $endLine = $startLine + substr_count((string) $token[1], "\n");
        $this->disableLine($startLine, $copNames);

        $nextLine = $this->nextSignificantLineAfter($endLine, $significantLines);
        if ($nextLine !== null) {
            $this->disableLine($nextLine, $copNames);
        }
    }

    /** @return list<string> */
    private function directiveCopNames(string $comment): array
    {
        if (!preg_match('/phpubocop:disable\s+([A-Za-z0-9_\/,\s-]+)/i', $comment, $matches)) {
            return [];
        }

        $rawNames = preg_split('/\s*,\s*/', trim((string) $matches[1])) ?: [];
        $copNames = [];
        foreach ($rawNames as $rawName) {
            $normalized = strtolower(trim((string) $rawName));
            if ($normalized === '') {
                continue;
            }

            $copNames[$normalized] = true;
        }

        return array_keys($copNames);
    }

    /** @param list<string> $copNames */
    private function disableLine(int $line, array $copNames): void
    {
        foreach ($copNames as $copName) {
            $this->disabledByLine[$line][$copName] = true;
        }
    }

    /** @param array<int,bool> $significantLines */
    private function nextSignificantLineAfter(int $line, array $significantLines): ?int
    {
        $candidates = array_keys($significantLines);
        sort($candidates);
        foreach ($candidates as $candidate) {
            if ($candidate > $line) {
                return $candidate;
            }
        }

        return null;
    }

    private function isCommentToken(int $tokenId): bool
    {
        return $tokenId === T_COMMENT || $tokenId === T_DOC_COMMENT;
    }

    private function ignoredSignificantToken(int $tokenId): bool
    {
        return in_array($tokenId, [
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
        ], true);
    }
}
