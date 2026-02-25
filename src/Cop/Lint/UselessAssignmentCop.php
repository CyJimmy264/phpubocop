<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class UselessAssignmentCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/UselessAssignment';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$this->isScope($node)) {
                return;
            }

            foreach ($this->analyzeScope($node, $file->path) as $offense) {
                $offenses[] = $offense;
            }
        });

        return $offenses;
    }

    /** @return list<Offense> */
    private function analyzeScope(Node $scope, string $path): array
    {
        $offenses = [];
        /** @var array<string,array{line:int,depth:int}> $pendingAssignment */
        $pendingAssignment = [];

        $markRead = function (string $name) use (&$pendingAssignment): void {
            unset($pendingAssignment[$name]);
        };

        $markAssignment = function (string $name, int $line, int $depth) use (&$pendingAssignment, &$offenses, $path): void {
            if (isset($pendingAssignment[$name]) && $pendingAssignment[$name]['depth'] === $depth) {
                $offenses[] = new Offense(
                    $this->name(),
                    $path,
                    $pendingAssignment[$name]['line'],
                    1,
                    sprintf('Useless assignment to $%s. Value is overwritten before being read.', $name)
                );
            }

            $pendingAssignment[$name] = ['line' => $line, 'depth' => $depth];
        };

        $visit = function (Node $node, int $depth = 0) use (&$visit, $scope, $markRead, $markAssignment): void {
            if ($node !== $scope && $this->isScope($node)) {
                return;
            }

            if ($node instanceof Expr\Assign) {
                $visit($node->expr, $depth);
                $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $depth, $markAssignment);
                return;
            }

            if ($node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
                $this->markReadVariable($node->var, $markRead);
                $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $depth, $markAssignment);
                if ($node instanceof Expr\AssignOp) {
                    $visit($node->expr, $depth);
                } else {
                    $visit($node->expr, $depth);
                }
                return;
            }

            if ($node instanceof Expr\Variable && is_string($node->name)) {
                $markRead($node->name);
                return;
            }

            $nextDepth = $depth + ($this->isControlFlowNode($node) ? 1 : 0);

            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->{$subNodeName};

                if ($subNode instanceof Node) {
                    $visit($subNode, $nextDepth);
                    continue;
                }

                if (is_array($subNode)) {
                    foreach ($subNode as $child) {
                        if ($child instanceof Node) {
                            $visit($child, $nextDepth);
                        }
                    }
                }
            }
        };

        $visit($scope, 0);

        return $offenses;
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }

    private function isControlFlowNode(Node $node): bool
    {
        return $node instanceof Node\Stmt\If_
            || $node instanceof Node\Stmt\ElseIf_
            || $node instanceof Node\Stmt\Else_
            || $node instanceof Node\Stmt\For_
            || $node instanceof Node\Stmt\Foreach_
            || $node instanceof Node\Stmt\While_
            || $node instanceof Node\Stmt\Do_
            || $node instanceof Node\Stmt\Switch_
            || $node instanceof Node\Stmt\Case_
            || $node instanceof Node\Stmt\TryCatch
            || $node instanceof Node\Stmt\Catch_
            || $node instanceof Expr\Ternary
            || $node instanceof Expr\BinaryOp\BooleanAnd
            || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\BooleanOr
            || $node instanceof Expr\BinaryOp\LogicalOr
            || $node instanceof Expr\BinaryOp\LogicalXor
            || $node instanceof Expr\BinaryOp\Coalesce
            || $node instanceof Expr\Match_;
    }

    /** @param callable(string): void $markRead */
    private function markReadVariable(Node $target, callable $markRead): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $markRead($target->name);
            return;
        }

        foreach ($target->getSubNodeNames() as $subNodeName) {
            $subNode = $target->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->markReadVariable($subNode, $markRead);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->markReadVariable($child, $markRead);
                    }
                }
            }
        }
    }

    /** @param callable(string,int,int): void $markAssignment */
    private function markAssignedVariable(Node $target, int $line, int $depth, callable $markAssignment): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $markAssignment($target->name, $line, $depth);
        }
    }
}
