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
    private int $assignments = 0;
    private int $branches = 0;
    private int $conditions = 0;

    /** @var list<class-string<Node>> */
    private const CONDITION_NODES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\Case_::class,
        Stmt\Catch_::class,
        Expr\Ternary::class,
        Expr\BinaryOp\BooleanAnd::class,
        Expr\BinaryOp\BooleanOr::class,
        Expr\BinaryOp\LogicalAnd::class,
        Expr\BinaryOp\LogicalOr::class,
        Expr\BinaryOp\Equal::class,
        Expr\BinaryOp\NotEqual::class,
        Expr\BinaryOp\Identical::class,
        Expr\BinaryOp\NotIdentical::class,
        Expr\BinaryOp\Smaller::class,
        Expr\BinaryOp\SmallerOrEqual::class,
        Expr\BinaryOp\Greater::class,
        Expr\BinaryOp\GreaterOrEqual::class,
        Expr\BinaryOp\Spaceship::class,
    ];

    public function name(): string
    {
        return 'Metrics/AbcSize';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (float) ($config['Max'] ?? 17.0);
        $offenses = [];

        foreach ($file->astNodes() as $node) {
            if (!$this->isMeasuredScope($node)) {
                continue;
            }

            $this->appendOffenseForScopeIfNeeded($node, $file, $max, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function appendOffenseForScopeIfNeeded(
        Node $scope,
        SourceFile $file,
        float $max,
        array &$offenses,
    ): void {
        [$a, $b, $c] = $this->calculateAbcVector($scope);
        $size = round(sqrt(($a ** 2) + ($b ** 2) + ($c ** 2)), 2);
        if ($size <= $max) {
            return;
        }

        $offenses[] = new Offense(
            $this->name(),
            $file->path,
            (int) $scope->getStartLine(),
            1,
            sprintf(
                'Assignment Branch Condition size for %s is too high. [<%d, %d, %d> %.2f/%.2f]',
                $this->scopeName($scope),
                $a,
                $b,
                $c,
                $size,
                $max,
            ),
        );
    }

    private function isMeasuredScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod;
    }

    /** @return array{0:int,1:int,2:int} */
    private function calculateAbcVector(Node $scope): array
    {
        $this->assignments = 0;
        $this->branches = 0;
        $this->conditions = 0;
        $this->visitScopeNode($scope, $scope);
        return [$this->assignments, $this->branches, $this->conditions];
    }

    private function visitScopeNode(Node $node, Node $scope): void
    {
        if ($node !== $scope && $this->isMeasuredScope($node)) {
            return;
        }

        if ($this->isAssignment($node)) {
            $this->assignments++;
        }
        if ($this->isBranch($node)) {
            $this->branches++;
        }
        if ($this->isCondition($node)) {
            $this->conditions++;
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->visitScopeNode($child, $scope);
        }
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
        foreach (self::CONDITION_NODES as $conditionClass) {
            if ($node instanceof $conditionClass) {
                return true;
            }
        }

        return false;
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

    /** @return list<Node> */
    private function childNodesOf(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $children[] = $subNode;
                continue;
            }
            if (is_array($subNode)) {
                $this->appendChildNodes($children, $subNode);
            }
        }

        return $children;
    }

    /**
     * @param list<Node> $children
     * @param array<int,mixed> $subNode
     */
    private function appendChildNodes(array &$children, array $subNode): void
    {
        foreach ($subNode as $child) {
            if ($child instanceof Node) {
                $children[] = $child;
            }
        }
    }
}
