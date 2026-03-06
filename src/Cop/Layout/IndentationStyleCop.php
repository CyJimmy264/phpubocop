<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class IndentationStyleCop implements CopInterface, AutocorrectableCopInterface, SafeAutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Layout/IndentationStyle';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->isSpacesStyle($config)) {
            return [];
        }

        $ignoredLines = $this->ignoredIndentationLines($file->content);
        return $this->collectTabIndentOffenses($file, $ignoredLines);
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        if (!$this->isSpacesStyle($config)) {
            return $file->content;
        }

        $tabWidth = $this->tabWidth($config);
        $ignoredLines = $this->ignoredIndentationLines($file->content);
        $lines = explode("\n", $file->content);
        return $this->autocorrectLines($lines, $ignoredLines, $tabWidth);
    }

    /** @return array<int,true> */
    private function ignoredIndentationLines(string $content): array
    {
        $ignored = [];
        $heredocStartLine = null;

        foreach (token_get_all($content) as $token) {
            $heredocStartLine = $this->processIndentationToken($token, $ignored, $heredocStartLine);
        }

        return $ignored;
    }

    /** @param array<int,true> $ignoredLines @return list<Offense> */
    private function collectTabIndentOffenses(SourceFile $file, array $ignoredLines): array
    {
        $offenses = [];
        foreach ($file->lines() as $index => $line) {
            $lineNumber = $index + 1;
            if ($this->isIgnoredLine($ignoredLines, $lineNumber)) {
                continue;
            }

            $tabColumn = $this->tabIndentColumn($line);
            if ($tabColumn !== null) {
                $offenses[] = $this->tabIndentOffense($file, $lineNumber, $tabColumn);
            }
        }

        return $offenses;
    }

    /**
     * @param list<string> $lines
     * @param array<int,true> $ignoredLines
     */
    private function autocorrectLines(array $lines, array $ignoredLines, int $tabWidth): string
    {
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            if ($this->isIgnoredLine($ignoredLines, $lineNumber)) {
                continue;
            }

            $indent = $this->leadingIndent($line);
            if ($indent !== null && str_contains($indent, "\t")) {
                $lines[$index] = $this->replaceIndentTabs($line, $indent, $tabWidth);
            }
        }

        return implode("\n", $lines);
    }

    private function isSpacesStyle(array $config): bool
    {
        return strtolower((string) ($config['Style'] ?? 'spaces')) === 'spaces';
    }

    private function tabWidth(array $config): int
    {
        $tabWidth = (int) ($config['TabWidth'] ?? 4);
        return $tabWidth < 1 ? 4 : $tabWidth;
    }

    private function leadingIndent(string $line): ?string
    {
        if (preg_match('/^[ \t]+/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    /** @param array<int,true> $ignoredLines */
    private function isIgnoredLine(array $ignoredLines, int $lineNumber): bool
    {
        return isset($ignoredLines[$lineNumber]);
    }

    private function tabIndentColumn(string $line): ?int
    {
        $indent = $this->leadingIndent($line);
        if ($indent === null) {
            return null;
        }

        $tabPos = strpos($indent, "\t");
        return $tabPos === false ? null : $tabPos + 1;
    }

    private function tabIndentOffense(SourceFile $file, int $lineNumber, int $column): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $lineNumber,
            $column,
            'Use spaces for indentation; tabs are not allowed.',
        );
    }

    private function replaceIndentTabs(string $line, string $indent, int $tabWidth): string
    {
        $fixedIndent = str_replace("\t", str_repeat(' ', $tabWidth), $indent);
        return $fixedIndent . substr($line, strlen($indent));
    }

    /** @param array<int,true> $ignored */
    private function markHeredocBodyLines(array &$ignored, ?int $heredocStartLine, int $endLine): void
    {
        if ($heredocStartLine === null) {
            return;
        }

        for ($line = $heredocStartLine + 1; $line < $endLine; $line++) {
            $ignored[$line] = true;
        }
    }

    /** @param array<int,true> $ignored */
    private function processIndentationToken(mixed $token, array &$ignored, ?int $heredocStartLine): ?int
    {
        if (!is_array($token)) {
            return $heredocStartLine;
        }

        [$tokenId, , $line] = $token;
        if ($tokenId === T_START_HEREDOC) {
            return $line;
        }
        if ($tokenId === T_END_HEREDOC) {
            $this->markHeredocBodyLines($ignored, $heredocStartLine, $line);
            return null;
        }

        return $heredocStartLine;
    }
}
