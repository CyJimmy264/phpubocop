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
        $style = strtolower((string) ($config['Style'] ?? 'spaces'));
        if ($style !== 'spaces') {
            return [];
        }

        $ignoredLines = $this->ignoredIndentationLines($file->content);
        $offenses = [];

        foreach ($file->lines() as $index => $line) {
            $lineNumber = $index + 1;
            if (isset($ignoredLines[$lineNumber])) {
                continue;
            }

            if (preg_match('/^[ \t]+/', $line, $matches) !== 1) {
                continue;
            }

            $indent = $matches[0];
            $tabPos = strpos($indent, "\t");
            if ($tabPos === false) {
                continue;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                $lineNumber,
                $tabPos + 1,
                'Use spaces for indentation; tabs are not allowed.',
            );
        }

        return $offenses;
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        $style = strtolower((string) ($config['Style'] ?? 'spaces'));
        if ($style !== 'spaces') {
            return $file->content;
        }

        $tabWidth = (int) ($config['TabWidth'] ?? 4);
        if ($tabWidth < 1) {
            $tabWidth = 4;
        }

        $ignoredLines = $this->ignoredIndentationLines($file->content);
        $lines = explode("\n", $file->content);

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            if (isset($ignoredLines[$lineNumber])) {
                continue;
            }

            if (preg_match('/^[ \t]+/', $line, $matches) !== 1) {
                continue;
            }

            $indent = $matches[0];
            if (!str_contains($indent, "\t")) {
                continue;
            }

            $fixedIndent = str_replace("\t", str_repeat(' ', $tabWidth), $indent);
            $lines[$index] = $fixedIndent . substr($line, strlen($indent));
        }

        return implode("\n", $lines);
    }

    /** @return array<int,true> */
    private function ignoredIndentationLines(string $content): array
    {
        $ignored = [];
        $heredocStartLine = null;

        foreach (token_get_all($content) as $token) {
            if (!is_array($token)) {
                continue;
            }

            [$tokenId, , $line] = $token;

            if ($tokenId === T_START_HEREDOC) {
                $heredocStartLine = $line;
                continue;
            }

            if ($tokenId === T_END_HEREDOC && $heredocStartLine !== null) {
                for ($i = $heredocStartLine + 1; $i < $line; $i++) {
                    $ignored[$i] = true;
                }
                $heredocStartLine = null;
            }
        }

        return $ignored;
    }
}
