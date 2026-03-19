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
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

final class StrictComparisonCop implements
    CopInterface,
    AutocorrectableCopInterface,
    SafeAutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Style/StrictComparison';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            $this->appendOffenseForNode($node, $file, $offenses);
        });

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function appendOffenseForNode(Node $node, SourceFile $file, array &$offenses): void
    {
        if ($node instanceof Expr\BinaryOp\Equal) {
            $offenses[] = $this->newOffense($file, $node, 'Prefer strict comparison (===) over ==.');
            return;
        }
        if ($node instanceof Expr\BinaryOp\NotEqual) {
            $offenses[] = $this->newOffense($file, $node, 'Prefer strict comparison (!==) over !=.');
        }
    }

    private function newOffense(SourceFile $file, Node $node, string $message): Offense
    {
        $safe = $this->isSafeAutocorrectComparison($node);
        return new Offense(
            $this->name(),
            $file->path,
            (int) $node->getStartLine(),
            1,
            $message,
            'convention',
            $safe,
            $safe,
        );
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

    /** @return list<array{start:int,length:int,replacement:string}> */
    private function collectReplacements(SourceFile $file): array
    {
        $replacements = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$replacements, $file): void {
            $replacement = $this->replacementForNode($node, $file->content);
            if ($replacement !== null) {
                $replacements[] = $replacement;
            }
        });

        return $replacements;
    }

    /** @return array{start:int,length:int,replacement:string}|null */
    private function replacementForNode(Node $node, string $content): ?array
    {
        if (!$this->isSafeNodeForReplacement($node)) {
            return null;
        }

        $betweenContext = $this->betweenContext($node, $content);
        if ($betweenContext === null) {
            return null;
        }

        [$betweenStart, $operatorMatch] = $betweenContext;
        if ($operatorMatch === null) {
            return null;
        }

        return $this->buildReplacement($betweenStart, $operatorMatch[0], $operatorMatch[1]);
    }

    private function isSafeNodeForReplacement(Node $node): bool
    {
        return $this->isLooseComparison($node) && $this->isSafeAutocorrectComparison($node);
    }

    /** @return array{0:int,1:array{0:string,1:int}|null}|null */
    private function betweenContext(Node $node, string $content): ?array
    {
        $betweenRange = $this->betweenRange($node);
        if ($betweenRange === null) {
            return null;
        }

        [$start, $length] = $betweenRange;
        $between = $this->betweenString($content, $start, $length);
        if ($between === null) {
            return null;
        }

        return [$start, $this->operatorMatch($between)];
    }

    private function betweenString(string $content, int $start, int $length): ?string
    {
        $between = substr($content, $start, $length);
        if (!is_string($between) || $between === '') {
            return null;
        }

        return $between;
    }

    /** @return array{0:int,1:int}|null */
    private function betweenRange(Node $node): ?array
    {
        $leftEnd = $node->left->getEndFilePos();
        $rightStart = $node->right->getStartFilePos();
        if (!is_int($leftEnd) || !is_int($rightStart) || $rightStart <= $leftEnd) {
            return null;
        }

        return [$leftEnd + 1, $rightStart - $leftEnd - 1];
    }

    /** @return array{0:string,1:int}|null */
    private function operatorMatch(string $between): ?array
    {
        if (preg_match('/(!=|==)/', $between, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return [(string) $matches[1][0], (int) $matches[1][1]];
    }

    /** @return array{start:int,length:int,replacement:string} */
    private function buildReplacement(int $betweenStart, string $operator, int $offset): array
    {
        return [
            'start' => $betweenStart + $offset,
            'length' => strlen($operator),
            'replacement' => $operator === '==' ? '===' : '!==',
        ];
    }

    /** @param list<array{start:int,length:int,replacement:string}> $replacements */
    private function applyReplacements(string $content, array $replacements): string
    {
        foreach ($replacements as $replacement) {
            $content = substr($content, 0, $replacement['start'])
                . $replacement['replacement']
                . substr($content, $replacement['start'] + $replacement['length']);
        }

        return $content;
    }

    private function isLooseComparison(Node $node): bool
    {
        return $node instanceof Expr\BinaryOp\Equal || $node instanceof Expr\BinaryOp\NotEqual;
    }

    private function isSafeAutocorrectComparison(Node $node): bool
    {
        $leftType = $this->literalType($node->left);
        $rightType = $this->literalType($node->right);

        return $leftType !== null && $leftType === $rightType;
    }

    private function literalType(Node $node): ?string
    {
        return $this->scalarLiteralType($node) ?? $this->constLiteralType($node);
    }

    private function scalarLiteralType(Node $node): ?string
    {
        if ($node instanceof Scalar\Int_) {
            return 'int';
        }
        if ($node instanceof Scalar\Float_) {
            return 'float';
        }
        if ($node instanceof Scalar\String_) {
            return 'string';
        }

        return null;
    }

    private function constLiteralType(Node $node): ?string
    {
        if (!$node instanceof Expr\ConstFetch) {
            return null;
        }

        $name = strtolower($node->name->toString());
        if (in_array($name, ['true', 'false'], true)) {
            return 'bool';
        }
        if ($name === 'null') {
            return 'null';
        }

        return null;
    }
}
