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
    public function name(): string
    {
        return 'Metrics/PerceivedComplexity';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 8);
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
                        'Perceived complexity for %s is too high. [%d/%d]',
                        $this->scopeName($node),
                        $complexity,
                        $max
                    )
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
        $score = 1.0;

        $visit = function (Node $node) use (&$visit, &$score, $scope): void {
            if ($node !== $scope && $this->isMeasuredScope($node)) {
                return;
            }

            $score += $this->complexityScoreFor($node);

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
        return (int) round($score);
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

        if ($node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Catch_
            || $node instanceof Expr\Ternary
            || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\BooleanOr
            || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\LogicalOr) {
            return 1.0;
        }

        return 0.0;
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
}
