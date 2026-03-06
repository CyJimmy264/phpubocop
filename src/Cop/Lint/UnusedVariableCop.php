<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;

final class UnusedVariableCop implements CopInterface
{
    /** @var array<string,int> */
    private array $assigned = [];
    /** @var array<string,bool> */
    private array $read = [];
    private bool $hasDynamicExtraction = false;
    private bool $ignoreParameters = true;

    public function name(): string
    {
        return 'Lint/UnusedVariable';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $ignorePrefixedUnderscore = (bool) ($config['IgnorePrefixedUnderscore'] ?? true);
        $this->ignoreParameters = (bool) ($config['IgnoreParameters'] ?? true);

        $offenses = [];
        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file, $offenses, $ignorePrefixedUnderscore);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(
        Node $node,
        SourceFile $file,
        array &$offenses,
        bool $ignorePrefixedUnderscore,
    ): void {
        if ($this->isScope($node)) {
            $this->appendScopeOffenses($node, $file, $offenses, $ignorePrefixedUnderscore);
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->collectOffenses($child, $file, $offenses, $ignorePrefixedUnderscore);
        }
    }

    /** @param list<Offense> $offenses */
    private function appendScopeOffenses(
        Node $scope,
        SourceFile $file,
        array &$offenses,
        bool $ignorePrefixedUnderscore,
    ): void {
        foreach ($this->analyzeScope($scope, $ignorePrefixedUnderscore) as $unused) {
            [$name, $line] = $unused;
            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                $line,
                1,
                sprintf('Variable $%s is assigned but never used.', $name),
            );
        }
    }

    /** @return list<array{0:string,1:int}> */
    private function analyzeScope(Node $scope, bool $ignorePrefixedUnderscore): array
    {
        $this->assigned = [];
        $this->read = [];
        $this->hasDynamicExtraction = false;

        $this->visitScopeNode($scope, $scope);
        if ($this->hasDynamicExtraction) {
            return [];
        }

        return $this->buildUnusedList($ignorePrefixedUnderscore);
    }

    private function visitScopeNode(Node $node, Node $scope): void
    {
        if ($this->shouldSkipNodeInCurrentScope($node, $scope)) {
            return;
        }

        if ($this->handleNodeByType($node, $scope)) {
            return;
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $this->handleFunctionCallNode($node);
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->visitScopeNode($child, $scope);
        }
    }

    private function shouldSkipNodeInCurrentScope(Node $node, Node $scope): bool
    {
        return $node !== $scope && $this->isScope($node);
    }

    private function handleNodeByType(Node $node, Node $scope): bool
    {
        return $this->handleParamType($node, $scope)
            || $this->handleAssignType($node, $scope)
            || $this->handleAssignOpType($node, $scope)
            || $this->handleIncDecType($node)
            || $this->handleForeachType($node, $scope)
            || $this->handleCatchType($node, $scope)
            || $this->handleVariableType($node, $scope);
    }

    private function handleParamType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Param) {
            return false;
        }
        $this->handleParamNode($node, $scope);
        return true;
    }

    private function handleAssignType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Expr\Assign) {
            return false;
        }
        $this->handleAssignNode($node, $scope);
        return true;
    }

    private function handleAssignOpType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Expr\AssignOp && !$node instanceof Expr\AssignRef) {
            return false;
        }
        $this->handleAssignOpLikeNode($node, $scope);
        return true;
    }

    private function handleIncDecType(Node $node): bool
    {
        if (!$this->isIncDecNode($node)) {
            return false;
        }
        $this->handleIncDecNode($node);
        return true;
    }

    private function handleForeachType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Stmt\Foreach_) {
            return false;
        }
        $this->handleForeachNode($node, $scope);
        return true;
    }

    private function handleCatchType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Stmt\Catch_) {
            return false;
        }
        $this->handleCatchNode($node, $scope);
        return true;
    }

    private function handleVariableType(Node $node, Node $scope): bool
    {
        if (!$node instanceof Expr\Variable) {
            return false;
        }
        $this->handleVariableNode($node, $scope);
        return true;
    }

    private function handleParamNode(Param $node, Node $scope): void
    {
        if (!$this->ignoreParameters && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->markAssignedName($node->var->name, (int) $node->getStartLine());
        }

        if ($node->default instanceof Node) {
            $this->visitScopeNode($node->default, $scope);
        }
    }

    private function handleAssignNode(Expr\Assign $node, Node $scope): void
    {
        $this->collectReadNamesFromAssignmentTarget($node->var);
        $this->collectAssignedNames($node->var, (int) $node->getStartLine());
        $this->visitScopeNode($node->expr, $scope);
    }

    private function handleAssignOpLikeNode(Expr\AssignOp|Expr\AssignRef $node, Node $scope): void
    {
        $this->collectReadNames($node->var);
        $this->collectAssignedNames($node->var, (int) $node->getStartLine());
        $this->visitScopeNode($node->expr, $scope);
    }

    private function isIncDecNode(Node $node): bool
    {
        return $node instanceof Expr\PreInc
            || $node instanceof Expr\PreDec
            || $node instanceof Expr\PostInc
            || $node instanceof Expr\PostDec;
    }

    private function handleIncDecNode(Node $node): void
    {
        if (
            !$node instanceof Expr\PreInc
            && !$node instanceof Expr\PreDec
            && !$node instanceof Expr\PostInc
            && !$node instanceof Expr\PostDec
        ) {
            return;
        }

        $this->collectReadNames($node->var);
        $this->collectAssignedNames($node->var, (int) $node->getStartLine());
    }

    private function handleForeachNode(Stmt\Foreach_ $node, Node $scope): void
    {
        $this->visitScopeNode($node->expr, $scope);

        if ($node->keyVar instanceof Node) {
            $this->collectAssignedNames($node->keyVar, (int) $node->keyVar->getStartLine());
        }
        if ($node->valueVar instanceof Node) {
            $this->collectAssignedNames($node->valueVar, (int) $node->valueVar->getStartLine());
        }

        foreach ($node->stmts as $foreachStmt) {
            $this->visitScopeNode($foreachStmt, $scope);
        }
    }

    private function handleCatchNode(Stmt\Catch_ $node, Node $scope): void
    {
        if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->markAssignedName($node->var->name, (int) $node->var->getStartLine());
        }

        foreach ($node->stmts as $catchStmt) {
            $this->visitScopeNode($catchStmt, $scope);
        }
    }

    private function handleVariableNode(Expr\Variable $node, Node $scope): void
    {
        if (is_string($node->name)) {
            $this->markReadName($node->name);
            return;
        }

        if ($node->name instanceof Node) {
            $this->visitScopeNode($node->name, $scope);
        }
    }

    private function handleFunctionCallNode(Expr\FuncCall $node): void
    {
        $functionName = strtolower($node->name->toString());
        if ($functionName === 'compact') {
            $this->markCompactVariableReads($node);
            return;
        }
        if ($functionName === 'extract') {
            $this->hasDynamicExtraction = true;
            return;
        }
        if ($functionName === 'parse_str' && count($node->args) < 2) {
            $this->hasDynamicExtraction = true;
        }
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }

    private function collectAssignedNames(Node $target, int $line): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $this->markAssignedName($target->name, $line);
            return;
        }

        foreach ($this->assignmentTargetsFromComposite($target) as $assignmentTarget) {
            $this->collectAssignedNames($assignmentTarget['node'], $assignmentTarget['line']);
        }
    }

    private function collectReadNamesFromAssignmentTarget(Node $target): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            return;
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            return;
        }

        $this->collectReadNames($target);
    }

    private function collectReadNames(Node $target): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $this->markReadName($target->name);
            return;
        }

        foreach ($this->childNodesOf($target) as $child) {
            $this->collectReadNames($child);
        }
    }

    private function isTrackableName(string $name): bool
    {
        return $name !== '' && !in_array($name, ['this', 'GLOBALS'], true);
    }

    private function markCompactVariableReads(Expr\FuncCall $call): void
    {
        foreach ($call->args as $arg) {
            $value = $arg->value;
            if ($value instanceof String_) {
                $this->markReadName($value->value);
                continue;
            }
            if (!$value instanceof Expr\Array_) {
                continue;
            }

            foreach ($value->items as $item) {
                if ($item !== null && $item->value instanceof String_) {
                    $this->markReadName($item->value->value);
                }
            }
        }
    }

    private function markAssignedName(string $name, int $line): void
    {
        if (!$this->isTrackableName($name)) {
            return;
        }
        if (!array_key_exists($name, $this->assigned)) {
            $this->assigned[$name] = $line;
        }
    }

    private function markReadName(string $name): void
    {
        if ($this->isTrackableName($name)) {
            $this->read[$name] = true;
        }
    }

    /** @return list<array{0:string,1:int}> */
    private function buildUnusedList(bool $ignorePrefixedUnderscore): array
    {
        $unused = [];
        foreach ($this->assigned as $name => $line) {
            if ($ignorePrefixedUnderscore && str_starts_with($name, '_')) {
                continue;
            }
            if (!isset($this->read[$name])) {
                $unused[] = [$name, $line];
            }
        }

        usort($unused, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
        return $unused;
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
     * @param array<int,mixed> $subNode
     * @param list<Node> $children
     */
    private function appendChildNodes(array &$children, array $subNode): void
    {
        foreach ($subNode as $child) {
            if ($child instanceof Node) {
                $children[] = $child;
            }
        }
    }

    /** @return list<array{node:Node,line:int}> */
    private function assignmentTargetsFromComposite(Node $target): array
    {
        if (!$target instanceof Expr\List_ && !$target instanceof Expr\Array_) {
            return [];
        }

        $targets = [];
        foreach ($target->items as $item) {
            if ($item === null || !$item->value instanceof Node) {
                continue;
            }

            $targets[] = [
                'node' => $item->value,
                'line' => (int) $item->getStartLine(),
            ];
        }

        return $targets;
    }
}
