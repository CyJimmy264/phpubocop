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
    /** @var array<string,array{line:int,context:string}> */
    private array $pendingAssignment = [];
    /** @var list<Offense> */
    private array $scopeOffenses = [];
    private string $currentPath = '';

    /** @var list<class-string<Node>> */
    private const CONTROL_FLOW_NODES = [
        Node\Stmt\If_::class,
        Node\Stmt\ElseIf_::class,
        Node\Stmt\Else_::class,
        Node\Stmt\For_::class,
        Node\Stmt\Foreach_::class,
        Node\Stmt\While_::class,
        Node\Stmt\Do_::class,
        Node\Stmt\Switch_::class,
        Node\Stmt\Case_::class,
        Node\Stmt\TryCatch::class,
        Node\Stmt\Catch_::class,
        Expr\Ternary::class,
        Expr\BinaryOp\BooleanAnd::class,
        Expr\BinaryOp\LogicalAnd::class,
        Expr\BinaryOp\BooleanOr::class,
        Expr\BinaryOp\LogicalOr::class,
        Expr\BinaryOp\LogicalXor::class,
        Expr\BinaryOp\Coalesce::class,
        Expr\Match_::class,
    ];

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
        $this->currentPath = $path;
        $this->pendingAssignment = [];
        $this->scopeOffenses = [];

        $this->visitScopeNode($scope, $scope, '');
        return $this->scopeOffenses;
    }

    private function visitScopeNode(Node $node, Node $scope, string $context): void
    {
        if ($node !== $scope && $this->isScope($node)) {
            return;
        }

        if ($this->handleAssignmentNode($node, $scope, $context)) {
            return;
        }
        if ($this->handleVariableReadNode($node)) {
            return;
        }

        $nextContext = $this->nextContext($node, $context);
        $this->visitSubNodes($node, $scope, $nextContext);
    }

    private function visitSubNodes(Node $node, Node $scope, string $context): void
    {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $this->visitScopeNode($subNode, $scope, $context);
                continue;
            }

            if (!is_array($subNode)) {
                continue;
            }

            foreach ($subNode as $child) {
                if ($child instanceof Node) {
                    $this->visitScopeNode($child, $scope, $context);
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
        foreach (self::CONTROL_FLOW_NODES as $controlFlowClass) {
            if ($node instanceof $controlFlowClass) {
                return true;
            }
        }

        return false;
    }

    private function markReadVariable(Node $target): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            unset($this->pendingAssignment[$target->name]);
            return;
        }

        foreach ($this->childNodesOf($target) as $child) {
            $this->markReadVariable($child);
        }
    }

    private function markAssignedVariable(Node $target, int $line, string $context): void
    {
        if (!$target instanceof Expr\Variable || !is_string($target->name)) {
            return;
        }

        $name = $target->name;
        if (isset($this->pendingAssignment[$name]) && $this->pendingAssignment[$name]['context'] === $context) {
            $this->scopeOffenses[] = new Offense(
                $this->name(),
                $this->currentPath,
                $this->pendingAssignment[$name]['line'],
                1,
                sprintf('Useless assignment to $%s. Value is overwritten before being read.', $name),
            );
        }

        $this->pendingAssignment[$name] = ['line' => $line, 'context' => $context];
    }

    private function handleAssignmentNode(Node $node, Node $scope, string $context): bool
    {
        if ($node instanceof Expr\Assign) {
            $this->visitScopeNode($node->expr, $scope, $context);
            $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $context);
            return true;
        }

        if (!$node instanceof Expr\AssignOp && !$node instanceof Expr\AssignRef) {
            return false;
        }

        $this->markReadVariable($node->var);
        $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $context);
        $this->visitScopeNode($node->expr, $scope, $context);
        return true;
    }

    private function handleVariableReadNode(Node $node): bool
    {
        if (!$node instanceof Expr\Variable || !is_string($node->name)) {
            return false;
        }

        unset($this->pendingAssignment[$node->name]);
        return true;
    }

    /** @return list<Node> */
    private function childNodesOf(Node $target): array
    {
        $children = [];
        foreach ($target->getSubNodeNames() as $subNodeName) {
            $subNode = $target->{$subNodeName};
            if ($subNode instanceof Node) {
                $children[] = $subNode;
                continue;
            }

            if (is_array($subNode)) {
                $this->appendNodeChildren($children, $subNode);
            }
        }

        return $children;
    }

    /**
     * @param list<Node> $children
     * @param array<int,mixed> $subNode
     */
    private function appendNodeChildren(array &$children, array $subNode): void
    {
        foreach ($subNode as $child) {
            if ($child instanceof Node) {
                $children[] = $child;
            }
        }
    }
}
