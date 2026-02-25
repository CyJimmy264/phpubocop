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
    public function name(): string
    {
        return 'Lint/UnusedVariable';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $ignorePrefixedUnderscore = (bool) ($config['IgnorePrefixedUnderscore'] ?? true);
        $ignoreParameters = (bool) ($config['IgnoreParameters'] ?? true);

        $offenses = [];
        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file, $offenses, $ignorePrefixedUnderscore, $ignoreParameters);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(
        Node $node,
        SourceFile $file,
        array &$offenses,
        bool $ignorePrefixedUnderscore,
        bool $ignoreParameters
    ): void {
        if ($this->isScope($node)) {
            foreach ($this->analyzeScope($node, $ignorePrefixedUnderscore, $ignoreParameters) as $unused) {
                [$name, $line] = $unused;
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    $line,
                    1,
                    sprintf('Variable $%s is assigned but never used.', $name)
                );
            }
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->collectOffenses($subNode, $file, $offenses, $ignorePrefixedUnderscore, $ignoreParameters);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectOffenses($child, $file, $offenses, $ignorePrefixedUnderscore, $ignoreParameters);
                    }
                }
            }
        }
    }

    /** @return list<array{0:string,1:int}> */
    private function analyzeScope(Node $scope, bool $ignorePrefixedUnderscore, bool $ignoreParameters): array
    {
        $assigned = [];
        $read = [];
        $hasDynamicExtraction = false;

        $markAssigned = function (string $name, int $line) use (&$assigned): void {
            if (!$this->isTrackableName($name)) {
                return;
            }

            if (!array_key_exists($name, $assigned)) {
                $assigned[$name] = $line;
            }
        };

        $markRead = function (string $name) use (&$read): void {
            if (!$this->isTrackableName($name)) {
                return;
            }

            $read[$name] = true;
        };

        $visit = function (Node $node) use (&$visit, $scope, $ignoreParameters, $markAssigned, $markRead, &$hasDynamicExtraction): void {
            if ($node !== $scope && $this->isScope($node)) {
                return;
            }

            if ($node instanceof Param) {
                if (!$ignoreParameters && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                    $markAssigned($node->var->name, (int) $node->getStartLine());
                }

                if ($node->default instanceof Node) {
                    $visit($node->default);
                }

                return;
            }

            if ($node instanceof Expr\Assign) {
                $this->collectAssignedNames($node->var, (int) $node->getStartLine(), $markAssigned);
                $visit($node->expr);
                return;
            }

            if ($node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
                $this->collectReadNames($node->var, $markRead);
                $this->collectAssignedNames($node->var, (int) $node->getStartLine(), $markAssigned);

                if ($node instanceof Expr\AssignOp) {
                    $visit($node->expr);
                } else {
                    $visit($node->expr);
                }

                return;
            }

            if ($node instanceof Expr\PreInc
                || $node instanceof Expr\PreDec
                || $node instanceof Expr\PostInc
                || $node instanceof Expr\PostDec) {
                $this->collectReadNames($node->var, $markRead);
                $this->collectAssignedNames($node->var, (int) $node->getStartLine(), $markAssigned);
                return;
            }

            if ($node instanceof Stmt\Foreach_) {
                $visit($node->expr);
                if ($node->keyVar instanceof Node) {
                    $this->collectAssignedNames($node->keyVar, (int) $node->keyVar->getStartLine(), $markAssigned);
                }
                if ($node->valueVar instanceof Node) {
                    $this->collectAssignedNames($node->valueVar, (int) $node->valueVar->getStartLine(), $markAssigned);
                }
                foreach ($node->stmts as $stmt) {
                    $visit($stmt);
                }
                return;
            }

            if ($node instanceof Stmt\Catch_) {
                if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
                    $markAssigned($node->var->name, (int) $node->var->getStartLine());
                }

                foreach ($node->stmts as $stmt) {
                    $visit($stmt);
                }

                return;
            }

            if ($node instanceof Expr\Variable) {
                if (is_string($node->name)) {
                    $markRead($node->name);
                    return;
                }

                if ($node->name instanceof Node) {
                    $visit($node->name);
                }

                return;
            }

            if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
                $functionName = strtolower($node->name->toString());
                if ($functionName === 'compact') {
                    $this->markCompactVariableReads($node, $markRead);
                } elseif ($functionName === 'extract') {
                    // extract() creates variables dynamically; static unused-variable
                    // analysis in this scope becomes unreliable.
                    $hasDynamicExtraction = true;
                } elseif ($functionName === 'parse_str' && count($node->args) < 2) {
                    // parse_str($query) without second argument writes variables
                    // into local scope dynamically.
                    $hasDynamicExtraction = true;
                }
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

        if ($hasDynamicExtraction) {
            return [];
        }

        $unused = [];
        foreach ($assigned as $name => $line) {
            if ($ignorePrefixedUnderscore && str_starts_with($name, '_')) {
                continue;
            }

            if (!isset($read[$name])) {
                $unused[] = [$name, $line];
            }
        }

        usort($unused, static fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
        return $unused;
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }

    /** @param callable(string,int): void $markAssigned */
    private function collectAssignedNames(Node $target, int $line, callable $markAssigned): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $markAssigned($target->name, $line);
            return;
        }

        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            foreach ($target->items as $item) {
                if ($item === null || !$item->value instanceof Node) {
                    continue;
                }

                $this->collectAssignedNames($item->value, (int) $item->getStartLine(), $markAssigned);
            }
        }
    }

    /** @param callable(string): void $markRead */
    private function collectReadNames(Node $target, callable $markRead): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $markRead($target->name);
            return;
        }

        foreach ($target->getSubNodeNames() as $subNodeName) {
            $subNode = $target->{$subNodeName};

            if ($subNode instanceof Node) {
                $this->collectReadNames($subNode, $markRead);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectReadNames($child, $markRead);
                    }
                }
            }
        }
    }

    private function isTrackableName(string $name): bool
    {
        return $name !== '' && !in_array($name, ['this', 'GLOBALS'], true);
    }

    /** @param callable(string): void $markRead */
    private function markCompactVariableReads(Expr\FuncCall $call, callable $markRead): void
    {
        foreach ($call->args as $arg) {
            $value = $arg->value;
            if ($value instanceof String_) {
                $markRead($value->value);
                continue;
            }

            if (!$value instanceof Expr\Array_) {
                continue;
            }

            foreach ($value->items as $item) {
                if ($item === null || !$item->value instanceof String_) {
                    continue;
                }

                $markRead($item->value->value);
            }
        }
    }
}
