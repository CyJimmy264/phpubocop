<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;

final class TrailingCommaInMultilineCop implements CopInterface, AutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Layout/TrailingCommaInMultiline';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        foreach ($this->collectMissingTrailingComma($file) as $missing) {
            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                $missing['line'],
                1,
                'Put a trailing comma in multiline literals/calls to reduce diff noise.'
            );
        }

        return $offenses;
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        $positions = [];
        foreach ($this->collectMissingTrailingComma($file) as $missing) {
            $positions[$missing['insert_pos']] = true;
        }

        if ($positions === []) {
            return $file->content;
        }

        $content = $file->content;
        $offsets = array_keys($positions);
        rsort($offsets, SORT_NUMERIC);

        foreach ($offsets as $offset) {
            $content = substr($content, 0, $offset) . ',' . substr($content, $offset);
        }

        return $content;
    }

    /** @return list<array{insert_pos:int,line:int}> */
    private function collectMissingTrailingComma(SourceFile $file): array
    {
        $missing = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$missing, $file): void {
            if ($node instanceof Expr\Array_) {
                $this->checkList($file, $node->items, $node, $missing);
                return;
            }

            if ($node instanceof Expr\FuncCall
                || $node instanceof Expr\MethodCall
                || $node instanceof Expr\StaticCall
                || $node instanceof Expr\New_
                || $node instanceof Expr\NullsafeMethodCall) {
                $args = $node->args;
                if (is_array($args)) {
                    $this->checkList($file, $args, $node, $missing);
                }
            }
        });

        usort($missing, static fn (array $a, array $b): int => [$a['line'], $a['insert_pos']] <=> [$b['line'], $b['insert_pos']]);
        return $missing;
    }

    /**
     * @param array<int, ArrayItem|Arg|null> $items
     * @param list<array{insert_pos:int,line:int}> $missing
     */
    private function checkList(SourceFile $file, array $items, Node $container, array &$missing): void
    {
        $nonNull = array_values(array_filter($items, static fn ($item): bool => $item !== null));
        if ($nonNull === []) {
            return;
        }

        $first = $nonNull[0];
        $last = $nonNull[count($nonNull) - 1];

        if (!$first instanceof Node || !$last instanceof Node) {
            return;
        }

        $containerEndLine = (int) $container->getEndLine();
        if ((int) $first->getStartLine() >= $containerEndLine) {
            return;
        }

        // Do not enforce trailing comma when closing delimiter is on the same
        // line as the last item: avoids awkward forms like "],)->call()".
        if ((int) $last->getEndLine() === $containerEndLine) {
            return;
        }

        $lastEnd = $last->getEndFilePos();
        $containerEnd = $container->getEndFilePos();
        if (!is_int($lastEnd) || !is_int($containerEnd) || $containerEnd <= $lastEnd) {
            return;
        }

        $between = substr($file->content, $lastEnd + 1, $containerEnd - $lastEnd);
        if ($between === false) {
            return;
        }

        if ($this->hasTrailingComma($between)) {
            return;
        }

        $missing[] = [
            'insert_pos' => $lastEnd + 1,
            'line' => (int) $last->getEndLine(),
        ];
    }

    private function hasTrailingComma(string $between): bool
    {
        $withoutComments = preg_replace('/\/\*.*?\*\/|\/\/[^\n]*|#[^\n]*/s', '', $between);
        if (!is_string($withoutComments)) {
            $withoutComments = $between;
        }

        return str_contains($withoutComments, ',');
    }
}
