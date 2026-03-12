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
        foreach ($file->astNodes() as $node) {
            if (!$this->isScope($node)) {
                continue;
            }

            $this->appendScopeOffenses($node, $file, $offenses, $ignorePrefixedUnderscore);
        }

        return $offenses;
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
        $stack = [$node];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (!$current instanceof Node) {
                continue;
            }

            if ($this->shouldSkipNodeInCurrentScope($current, $scope)) {
                continue;
            }

            if ($this->handleNodeByType($current, $scope, $stack)) {
                continue;
            }

            if ($current instanceof Expr\FuncCall && $current->name instanceof Node\Name) {
                $this->handleFunctionCallNode($current);
            }

            $this->pushChildren($stack, $this->childNodesOf($current));
        }
    }

    private function shouldSkipNodeInCurrentScope(Node $node, Node $scope): bool
    {
        return $node !== $scope && $this->isScope($node);
    }

    /** @param list<Node> $stack */
    private function handleNodeByType(Node $node, Node $scope, array &$stack): bool
    {
        return $this->handleParamType($node, $stack)
            || $this->handleAssignType($node, $stack)
            || $this->handleAssignOpType($node, $stack)
            || $this->handleIncDecType($node)
            || $this->handleForeachType($node, $stack)
            || $this->handleCatchType($node, $stack)
            || $this->handleVariableType($node, $stack);
    }

    /** @param list<Node> $stack */
    private function handleParamType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Param) {
            return false;
        }
        $this->handleParamNode($node, $stack);
        return true;
    }

    /** @param list<Node> $stack */
    private function handleAssignType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Expr\Assign) {
            return false;
        }
        $this->handleAssignNode($node, $stack);
        return true;
    }

    /** @param list<Node> $stack */
    private function handleAssignOpType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Expr\AssignOp && !$node instanceof Expr\AssignRef) {
            return false;
        }
        $this->handleAssignOpLikeNode($node, $stack);
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

    /** @param list<Node> $stack */
    private function handleForeachType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Stmt\Foreach_) {
            return false;
        }
        $this->handleForeachNode($node, $stack);
        return true;
    }

    /** @param list<Node> $stack */
    private function handleCatchType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Stmt\Catch_) {
            return false;
        }
        $this->handleCatchNode($node, $stack);
        return true;
    }

    /** @param list<Node> $stack */
    private function handleVariableType(Node $node, array &$stack): bool
    {
        if (!$node instanceof Expr\Variable) {
            return false;
        }
        $this->handleVariableNode($node, $stack);
        return true;
    }

    /** @param list<Node> $stack */
    private function handleParamNode(Param $node, array &$stack): void
    {
        if (!$this->ignoreParameters && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->markAssignedName($node->var->name, (int) $node->getStartLine());
        }

        if ($node->default instanceof Node) {
            $stack[] = $node->default;
        }
    }

    /** @param list<Node> $stack */
    private function handleAssignNode(Expr\Assign $node, array &$stack): void
    {
        $this->collectReadNamesFromAssignmentTarget($node->var);
        $this->collectAssignedNames($node->var, (int) $node->getStartLine());
        $stack[] = $node->expr;
    }

    /** @param list<Node> $stack */
    private function handleAssignOpLikeNode(Expr\AssignOp|Expr\AssignRef $node, array &$stack): void
    {
        $this->collectReadNames($node->var);
        $this->collectAssignedNames($node->var, (int) $node->getStartLine());
        $stack[] = $node->expr;
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

    /** @param list<Node> $stack */
    private function handleForeachNode(Stmt\Foreach_ $node, array &$stack): void
    {
        $stack[] = $node->expr;

        if ($node->keyVar instanceof Node) {
            $this->collectAssignedNames($node->keyVar, (int) $node->keyVar->getStartLine());
        }
        if ($node->valueVar instanceof Node) {
            $this->collectAssignedNames($node->valueVar, (int) $node->valueVar->getStartLine());
        }

        $this->pushChildren($stack, $node->stmts);
    }

    /** @param list<Node> $stack */
    private function handleCatchNode(Stmt\Catch_ $node, array &$stack): void
    {
        if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->markAssignedName($node->var->name, (int) $node->var->getStartLine());
        }

        $this->pushChildren($stack, $node->stmts);
    }

    /** @param list<Node> $stack */
    private function handleVariableNode(Expr\Variable $node, array &$stack): void
    {
        if (is_string($node->name)) {
            $this->markReadName($node->name);
            return;
        }

        if ($node->name instanceof Node) {
            $stack[] = $node->name;
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

    /** @param list<Node> $stack @param list<Node> $children */
    private function pushChildren(array &$stack, array $children): void
    {
        for ($i = count($children) - 1; $i >= 0; $i--) {
            $stack[] = $children[$i];
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
