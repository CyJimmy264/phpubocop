<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class PerceivedComplexityCop implements CopInterface
{
    private float $score = 1.0;
    /** @var list<class-string<Node>> */
    private const SINGLE_POINT_COMPLEXITY_NODES = [
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\Catch_::class,
        Expr\Ternary::class,
        Expr\BinaryOp\BooleanAnd::class,
        Expr\BinaryOp\BooleanOr::class,
        Expr\BinaryOp\LogicalAnd::class,
        Expr\BinaryOp\LogicalOr::class,
    ];

    public function name(): string
    {
        return 'Metrics/PerceivedComplexity';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 8);
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
        int $max,
        array &$offenses,
    ): void {
        $complexity = $this->calculateComplexity($scope);
        if ($complexity <= $max) {
            return;
        }

        $offenses[] = new Offense(
            $this->name(),
            $file->path,
            (int) $scope->getStartLine(),
            1,
            sprintf(
                'Perceived complexity for %s is too high. [%d/%d]',
                $this->scopeName($scope),
                $complexity,
                $max,
            ),
        );
    }

    private function isMeasuredScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod;
    }

    private function calculateComplexity(Node $scope): int
    {
        $this->score = 1.0;
        $this->visitScopeNode($scope, $scope);
        return (int) round($this->score);
    }

    private function visitScopeNode(Node $node, Node $scope): void
    {
        if ($node !== $scope && $this->isMeasuredScope($node)) {
            return;
        }

        $this->score += $this->complexityScoreFor($node);
        foreach ($this->childNodesOf($node) as $child) {
            $this->visitScopeNode($child, $scope);
        }
    }

    private function complexityScoreFor(Node $node): float
    {
        if ($node instanceof Stmt\Switch_) {
            return $this->switchScore($node);
        }

        if ($node instanceof Stmt\If_) {
            return $node->else !== null ? 2.0 : 1.0;
        }

        if ($node instanceof Stmt\ElseIf_) {
            return 1.0;
        }

        if ($this->isSinglePointComplexityNode($node)) {
            return 1.0;
        }

        return 0.0;
    }

    private function isSinglePointComplexityNode(Node $node): bool
    {
        foreach (self::SINGLE_POINT_COMPLEXITY_NODES as $complexityClass) {
            if ($node instanceof $complexityClass) {
                return true;
            }
        }

        return false;
    }

    private function switchScore(Stmt\Switch_ $node): float
    {
        $branches = count($node->cases);
        if ($branches === 0) {
            return 0.0;
        }

        $hasDefault = false;
        foreach ($node->cases as $case) {
            if ($case->cond === null) {
                $hasDefault = true;
                break;
            }
        }

        $branchCount = $branches + ($hasDefault ? 1 : 0);
        return round((0.8 + ($branchCount * 0.2)));
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
