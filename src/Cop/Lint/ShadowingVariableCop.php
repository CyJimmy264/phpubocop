<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class ShadowingVariableCop implements CopInterface
{
    /** @var array<string,bool> */
    private array $known = [];
    /** @var list<Offense> */
    private array $scopeOffenses = [];
    private string $currentPath = '';

    public function name(): string
    {
        return 'Lint/ShadowingVariable';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];
        foreach ($file->astNodes() as $node) {
            if (!$this->isScope($node)) {
                continue;
            }

            foreach ($this->analyzeScope($node, $file->path) as $scopeOffense) {
                $offenses[] = $scopeOffense;
            }
        }

        return $offenses;
    }

    /** @return list<Offense> */
    private function analyzeScope(Node $scope, string $path): array
    {
        $this->known = [];
        $this->scopeOffenses = [];
        $this->currentPath = $path;

        $this->visitScopeNode($scope, $scope);
        return $this->scopeOffenses;
    }

    private function visitScopeNode(Node $node, Node $scope): void
    {
        if ($node !== $scope && $this->isScope($node)) {
            return;
        }

        if ($this->handleNodeByType($node, $scope)) {
            return;
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->visitScopeNode($child, $scope);
        }
    }

    private function handleNodeByType(Node $node, Node $scope): bool
    {
        return $this->handleParamNodeType($node)
            || $this->handleAssignNodeType($node, $scope)
            || $this->handleForeachNodeType($node, $scope)
            || $this->handleCatchNodeType($node);
    }

    private function handleParamNodeType(Node $node): bool
    {
        if (!$node instanceof Node\Param || !$node->var instanceof Expr\Variable || !is_string($node->var->name)) {
            return false;
        }

        $this->registerName($node->var->name);
        return true;
    }

    private function handleAssignNodeType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Expr\Assign || !$node->var instanceof Expr\Variable || !is_string($node->var->name)) {
            return false;
        }

        $this->registerName($node->var->name);
        $this->visitScopeNode($node->expr, $scope);
        return true;
    }

    private function handleForeachNodeType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Stmt\Foreach_) {
            return false;
        }

        $this->visitScopeNode($node->expr, $scope);
        $this->checkForeachKeyAndValueShadowing($node);

        foreach ($node->stmts as $foreachStmt) {
            $this->visitScopeNode($foreachStmt, $scope);
        }

        return true;
    }

    private function handleCatchNodeType(Node $node): bool
    {
        if (!$node instanceof Stmt\Catch_ || !$node->var instanceof Expr\Variable || !is_string($node->var->name)) {
            return false;
        }

        $this->checkAndRegisterShadowing($node->var->name, (int) $node->var->getStartLine());
        return true;
    }

    private function checkForeachKeyAndValueShadowing(Stmt\Foreach_ $node): void
    {
        foreach ([$node->keyVar, $node->valueVar] as $variableNode) {
            if (!$variableNode instanceof Expr\Variable || !is_string($variableNode->name)) {
                continue;
            }

            $this->checkAndRegisterShadowing($variableNode->name, (int) $variableNode->getStartLine());
        }
    }

    private function checkAndRegisterShadowing(string $name, int $line): void
    {
        if ($this->isKnown($name)) {
            $this->scopeOffenses[] = new Offense(
                $this->name(),
                $this->currentPath,
                $line,
                1,
                sprintf('Variable $%s shadows an existing variable in this scope.', $name),
            );
        }

        $this->registerName($name);
    }

    private function registerName(string $name): void
    {
        $this->known[$name] = true;
    }

    private function isKnown(string $name): bool
    {
        return isset($this->known[$name]);
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
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
