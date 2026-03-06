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
        /** @var array<string,array{line:int,context:string}> $pendingAssignment */
        $pendingAssignment = [];
        $offenses = [];
        $this->visitScopeNode($scope, $scope, '', $path, $pendingAssignment, $offenses);
        return $offenses;
    }

    /**
     * @param array<string,array{line:int,context:string}> $pendingAssignment
     * @param list<Offense> $offenses
     */
    private function visitScopeNode(
        Node $node,
        Node $scope,
        string $context,
        string $path,
        array &$pendingAssignment,
        array &$offenses,
    ): void {
        if ($node !== $scope && $this->isScope($node)) {
            return;
        }

        if ($node instanceof Expr\Assign) {
            $this->visitScopeNode($node->expr, $scope, $context, $path, $pendingAssignment, $offenses);
            $this->markAssignedVariable(
                $node->var,
                (int) $node->getStartLine(),
                $context,
                $path,
                $pendingAssignment,
                $offenses,
            );
            return;
        }

        if ($node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
            $this->markReadVariable($node->var, $pendingAssignment);
            $this->markAssignedVariable(
                $node->var,
                (int) $node->getStartLine(),
                $context,
                $path,
                $pendingAssignment,
                $offenses,
            );
            $this->visitScopeNode($node->expr, $scope, $context, $path, $pendingAssignment, $offenses);
            return;
        }

        if ($node instanceof Expr\Variable && is_string($node->name)) {
            unset($pendingAssignment[$node->name]);
            return;
        }

        $nextContext = $this->nextContext($node, $context);
        $this->visitSubNodes($node, $scope, $nextContext, $path, $pendingAssignment, $offenses);
    }

    /**
     * @param array<string,array{line:int,context:string}> $pendingAssignment
     * @param list<Offense> $offenses
     */
    private function visitSubNodes(
        Node $node,
        Node $scope,
        string $context,
        string $path,
        array &$pendingAssignment,
        array &$offenses,
    ): void {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $this->visitScopeNode($subNode, $scope, $context, $path, $pendingAssignment, $offenses);
                continue;
            }

            if (!is_array($subNode)) {
                continue;
            }

            foreach ($subNode as $child) {
                if ($child instanceof Node) {
                    $this->visitScopeNode($child, $scope, $context, $path, $pendingAssignment, $offenses);
                }
            }
        }
    }

    private function nextContext(Node $node, string $context): string
    {
        if ($this->isControlFlowNode($node)) {
            return $context . '/' . (string) spl_object_id($node);
        }

        return $context;
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

    /** @param array<string,array{line:int,context:string}> $pendingAssignment */
    private function markReadVariable(Node $target, array &$pendingAssignment): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            unset($pendingAssignment[$target->name]);
            return;
        }

        foreach ($target->getSubNodeNames() as $subNodeName) {
            $subNode = $target->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->markReadVariable($subNode, $pendingAssignment);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->markReadVariable($child, $pendingAssignment);
                    }
                }
            }
        }
    }

    /**
     * @param array<string,array{line:int,context:string}> $pendingAssignment
     * @param list<Offense> $offenses
     */
    private function markAssignedVariable(
        Node $target,
        int $line,
        string $context,
        string $path,
        array &$pendingAssignment,
        array &$offenses,
    ): void
    {
        if (!$target instanceof Expr\Variable || !is_string($target->name)) {
            return;
        }

        $name = $target->name;
        if (isset($pendingAssignment[$name]) && $pendingAssignment[$name]['context'] === $context) {
            $offenses[] = new Offense(
                $this->name(),
                $path,
                $pendingAssignment[$name]['line'],
                1,
                sprintf('Useless assignment to $%s. Value is overwritten before being read.', $name),
            );
        }

        $pendingAssignment[$name] = ['line' => $line, 'context' => $context];
    }
}
