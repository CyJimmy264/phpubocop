<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;

final class TrailingCommaInMultilineCop implements
    CopInterface,
    AutocorrectableCopInterface,
    SafeAutocorrectableCopInterface
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
                'Put a trailing comma in multiline literals/calls to reduce diff noise.',
            );
        }

        return $offenses;
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        $positions = $this->missingInsertPositions($file);
        if ($positions === []) {
            return $file->content;
        }

        return $this->insertCommasAtPositions($file->content, $positions);
    }

    /** @return list<array{insert_pos:int,line:int}> */
    private function collectMissingTrailingComma(SourceFile $file): array
    {
        $missing = [];

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$missing, $file): void {
            if ($node instanceof Expr\Array_) {
                $this->checkList($file, $node->items, $node, $missing);
                return;
            }

            if ($this->isCallLikeNode($node)) {
                $this->checkCallLikeList($file, $node, $missing);
            }
        });

        usort(
            $missing,
            static fn (array $a, array $b): int => [$a['line'], $a['insert_pos']] <=> [$b['line'], $b['insert_pos']],
        );
        return $missing;
    }

    /**
     * @param array<int, ArrayItem|Arg|null> $items
     * @param list<array{insert_pos:int,line:int}> $missing
     */
    private function checkList(SourceFile $file, array $items, Node $container, array &$missing): void
    {
        $context = $this->buildListCheckContext($items, $container);
        if ($context === null) {
            return;
        }

        [$last, $positions] = $context;
        $between = $this->contentBetweenPositions($file->content, $positions);
        if ($between === null || $this->hasTrailingComma($between)) {
            return;
        }

        $missing[] = $this->missingEntryFromPositions($positions, (int) $last->getEndLine());
    }

    /** @param array<int, ArrayItem|Arg|null> $items @return array{0:Node,1:Node}|null */
    private function firstAndLastNode(array $items): ?array
    {
        $nonNull = array_values(array_filter($items, static fn ($item): bool => $item !== null));
        if ($nonNull === []) {
            return null;
        }

        $first = $nonNull[0];
        $last = $nonNull[count($nonNull) - 1];
        if (!$first instanceof Node || !$last instanceof Node) {
            return null;
        }

        return [$first, $last];
    }

    /** @param array<int, ArrayItem|Arg|null> $items @return array{0:Node,1:array{0:int,1:int}}|null */
    private function buildListCheckContext(array $items, Node $container): ?array
    {
        $firstAndLast = $this->firstAndLastNode($items);
        if ($firstAndLast === null) {
            return null;
        }

        [$first, $last] = $firstAndLast;
        if ($this->shouldSkipContainer($first, $last, $container)) {
            return null;
        }

        $positions = $this->extractBoundaryPositions($last, $container);
        if ($positions === null) {
            return null;
        }

        return [$last, $positions];
    }

    private function shouldSkipContainer(Node $first, Node $last, Node $container): bool
    {
        $containerEndLine = (int) $container->getEndLine();
        if ((int) $first->getStartLine() >= $containerEndLine) {
            return true;
        }

        // Do not enforce trailing comma when closing delimiter is on the same
        // line as the last item: avoids awkward forms like "],)->call()".
        return (int) $last->getEndLine() === $containerEndLine;
    }

    /** @return array{0:int,1:int}|null */
    private function extractBoundaryPositions(Node $last, Node $container): ?array
    {
        $lastEnd = $last->getEndFilePos();
        $containerEnd = $container->getEndFilePos();
        if (!is_int($lastEnd) || !is_int($containerEnd)) {
            return null;
        }
        if ($containerEnd <= $lastEnd) {
            return null;
        }

        return [$lastEnd, $containerEnd];
    }

    /** @param list<array{insert_pos:int,line:int}> $missing */
    private function checkCallLikeList(SourceFile $file, Node $node, array &$missing): void
    {
        $args = $node->args;
        if (!is_array($args)) {
            return;
        }

        $this->checkList($file, $args, $node, $missing);
    }

    private function isCallLikeNode(Node $node): bool
    {
        return $node instanceof Expr\FuncCall
            || $node instanceof Expr\MethodCall
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\New_
            || $node instanceof Expr\NullsafeMethodCall;
    }

    /** @return array<int,true> */
    private function missingInsertPositions(SourceFile $file): array
    {
        $positions = [];
        foreach ($this->collectMissingTrailingComma($file) as $missing) {
            $positions[$missing['insert_pos']] = true;
        }

        return $positions;
    }

    /** @param array<int,true> $positions */
    private function insertCommasAtPositions(string $content, array $positions): string
    {
        $offsets = array_keys($positions);
        rsort($offsets, SORT_NUMERIC);

        foreach ($offsets as $offset) {
            $content = substr($content, 0, $offset) . ',' . substr($content, $offset);
        }

        return $content;
    }

    /** @param array{0:int,1:int} $positions */
    private function contentBetweenPositions(string $content, array $positions): ?string
    {
        [$lastEnd, $containerEnd] = $positions;
        $between = substr($content, $lastEnd + 1, $containerEnd - $lastEnd);
        return is_string($between) ? $between : null;
    }

    /** @param array{0:int,1:int} $positions @return array{insert_pos:int,line:int} */
    private function missingEntryFromPositions(array $positions, int $line): array
    {
        [$lastEnd] = $positions;
        return [
            'insert_pos' => $lastEnd + 1,
            'line' => $line,
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
