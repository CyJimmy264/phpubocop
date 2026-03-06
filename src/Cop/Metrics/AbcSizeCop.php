<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class AbcSizeCop implements CopInterface
{
    public function name(): string
    {
        return 'Metrics/AbcSize';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (float) ($config['Max'] ?? 17.0);
        $offenses = [];

        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file, $max, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(Node $node, SourceFile $file, float $max, array &$offenses): void
    {
        if ($this->isMeasuredScope($node)) {
            [$a, $b, $c] = $this->calculateAbcVector($node);
            $size = round(sqrt(($a ** 2) + ($b ** 2) + ($c ** 2)), 2);

            if ($size > $max) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    sprintf(
                        'Assignment Branch Condition size for %s is too high. [<%d, %d, %d> %.2f/%.2f]',
                        $this->scopeName($node),
                        $a,
                        $b,
                        $c,
                        $size,
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

    /** @return array{0:int,1:int,2:int} */
    private function calculateAbcVector(Node $scope): array
    {
        $a = 0;
        $b = 0;
        $c = 0;

        $visit = function (Node $node) use (&$visit, &$a, &$b, &$c, $scope): void {
            if ($node !== $scope && $this->isMeasuredScope($node)) {
                return;
            }

            if ($this->isAssignment($node)) {
                $a++;
            }

            if ($this->isBranch($node)) {
                $b++;
            }

            if ($this->isCondition($node)) {
                $c++;
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
        return [$a, $b, $c];
    }

    private function isAssignment(Node $node): bool
    {
        return $node instanceof Expr\Assign
            || $node instanceof Expr\AssignOp
            || $node instanceof Expr\AssignRef;
    }

    private function isBranch(Node $node): bool
    {
        return $node instanceof Expr\FuncCall
            || $node instanceof Expr\MethodCall
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\NullsafeMethodCall;
    }

    private function isCondition(Node $node): bool
    {
        return $node instanceof Stmt\If_
            || $node instanceof Stmt\ElseIf_
            || $node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Case_
            || $node instanceof Stmt\Catch_
            || $node instanceof Expr\Ternary
            || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\BooleanOr
            || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\LogicalOr
            || $node instanceof Expr\BinaryOp\Equal
            || $node instanceof Expr\BinaryOp\NotEqual
            || $node instanceof Expr\BinaryOp\Identical
            || $node instanceof Expr\BinaryOp\NotIdentical
            || $node instanceof Expr\BinaryOp\Smaller
            || $node instanceof Expr\BinaryOp\SmallerOrEqual
            || $node instanceof Expr\BinaryOp\Greater
            || $node instanceof Expr\BinaryOp\GreaterOrEqual
            || $node instanceof Expr\BinaryOp\Spaceship;
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
