<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class CyclomaticComplexityCop implements CopInterface
{
    public function name(): string
    {
        return 'Metrics/CyclomaticComplexity';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 7);
        $offenses = [];

        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file, $max, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(Node $node, SourceFile $file, int $max, array &$offenses): void
    {
        if ($this->isMeasuredScope($node)) {
            $complexity = $this->calculateComplexity($node);
            if ($complexity > $max) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    sprintf(
                        'Cyclomatic complexity for %s is too high. [%d/%d]',
                        $this->scopeName($node),
                        $complexity,
                        $max,
                    ),
                );
            }
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->collectOffenses($subNode, $file, $max, $offenses);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectOffenses($child, $file, $max, $offenses);
                    }
                }
            }
        }
    }

    private function isMeasuredScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod;
    }

    private function calculateComplexity(Node $scope): int
    {
        $complexity = 1;

        $visit = function (Node $node) use (&$visit, &$complexity, $scope): void {
            if ($node !== $scope && $this->isMeasuredScope($node)) {
                return;
            }

            if ($this->addsComplexity($node)) {
                $complexity++;
            }

            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->{$subNodeName};

                if ($subNode instanceof Node) {
                    $visit($subNode);
                    continue;
                }

                if (is_array($subNode)) {
                    foreach ($subNode as $child) {
                        if ($child instanceof Node) {
                            $visit($child);
                        }
                    }
                }
            }
        };

        $visit($scope);
        return $complexity;
    }

    private function addsComplexity(Node $node): bool
    {
        return $node instanceof Stmt\If_
            || $node instanceof Stmt\ElseIf_
            || $node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Catch_
            || $node instanceof Stmt\Case_
            || $node instanceof Expr\Ternary
            || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\BooleanOr
            || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\LogicalOr;
    }

    private function scopeName(Node $node): string
    {
        if ($node instanceof Stmt\Function_) {
            return $node->name->toString();
        }

        if ($node instanceof Stmt\ClassMethod && $node->name !== null) {
            return $node->name->toString();
        }

        return 'anonymous';
    }
}
