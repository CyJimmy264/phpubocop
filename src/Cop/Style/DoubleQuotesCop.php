<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;

final class DoubleQuotesCop implements CopInterface, AutocorrectableCopInterface, SafeAutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Style/DoubleQuotes';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            if (!$this->isAutocorrectSafeDoubleQuotedString($file, $node)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Prefer single-quoted strings when interpolation is not needed.',
            );
        });

        return $offenses;
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        $replacements = $this->collectReplacements($file);
        if ($replacements === []) {
            return $file->content;
        }

        usort($replacements, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);
        return $this->applyReplacements($file->content, $replacements);
    }

    private function isAutocorrectSafeDoubleQuotedString(SourceFile $file, Node $node): bool
    {
        if (!$node instanceof String_) {
            return false;
        }

        return $this->isDoubleQuotedStringNode($node)
            && !$this->hasEscapesInRawLiteral($file->content, $node)
            && !str_contains($node->value, "'");
    }

    private function rawLiteral(string $content, String_ $node): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if (!is_int($start) || !is_int($end) || $end < $start) {
            return null;
        }

        return substr($content, $start, $end - $start + 1) ?: null;
    }

    private function isDoubleQuotedStringNode(String_ $node): bool
    {
        return $node->getAttribute('kind') === String_::KIND_DOUBLE_QUOTED;
    }

    private function hasEscapesInRawLiteral(string $content, String_ $node): bool
    {
        $rawLiteral = $this->rawLiteral($content, $node);
        return $rawLiteral !== null && str_contains($rawLiteral, '\\');
    }

    /** @return list<array{start:int,end:int,replacement:string}> */
    private function collectReplacements(SourceFile $file): array
    {
        $replacements = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$replacements, $file): void {
            $replacement = $this->replacementForNode($node, $file);
            if ($replacement !== null) {
                $replacements[] = $replacement;
            }
        });

        return $replacements;
    }

    /** @return array{start:int,end:int,replacement:string}|null */
    private function replacementForNode(Node $node, SourceFile $file): ?array
    {
        if (!$this->isAutocorrectSafeDoubleQuotedString($file, $node)) {
            return null;
        }

        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if (!is_int($start) || !is_int($end) || $end < $start) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
            'replacement' => "'" . $node->value . "'",
        ];
    }

    /** @param list<array{start:int,end:int,replacement:string}> $replacements */
    private function applyReplacements(string $content, array $replacements): string
    {
        foreach ($replacements as $replacement) {
            $content = substr($content, 0, $replacement['start'])
                . $replacement['replacement']
                . substr($content, $replacement['end'] + 1);
        }

        return $content;
    }
}
