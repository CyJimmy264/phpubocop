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
    public function name(): string
    {
        return 'Lint/ShadowingVariable';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file->path, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(Node $node, string $path, array &$offenses): void
    {
        if ($this->isScope($node)) {
            foreach ($this->analyzeScope($node, $path) as $offense) {
                $offenses[] = $offense;
            }
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $this->collectOffenses($subNode, $path, $offenses);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectOffenses($child, $path, $offenses);
                    }
                }
            }
        }
    }

    /** @return list<Offense> */
    private function analyzeScope(Node $scope, string $path): array
    {
        $offenses = [];
        $known = [];

        $register = function (string $name) use (&$known): void {
            $known[$name] = true;
        };

        $isKnown = function (string $name) use (&$known): bool {
            return isset($known[$name]);
        };

        $visit = function (Node $node) use (&$visit, $scope, $register, $isKnown, &$offenses, $path): void {
            if ($node !== $scope && $this->isScope($node)) {
                return;
            }

            if ($node instanceof Node\Param && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $register($node->var->name);
            }

            if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $register($node->var->name);
                $visit($node->expr);
                return;
            }

            if ($node instanceof Stmt\Foreach_) {
                $visit($node->expr);

                foreach ([$node->keyVar, $node->valueVar] as $varNode) {
                    if (!$varNode instanceof Expr\Variable || !is_string($varNode->name)) {
                        continue;
                    }

                    $name = $varNode->name;
                    if ($isKnown($name)) {
                        $offenses[] = new Offense(
                            $this->name(),
                            $path,
                            (int) $varNode->getStartLine(),
                            1,
                            sprintf('Variable $%s shadows an existing variable in this scope.', $name)
                        );
                    }

                    $register($name);
                }

                foreach ($node->stmts as $stmt) {
                    $visit($stmt);
                }

                return;
            }

            if ($node instanceof Stmt\Catch_ && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $name = $node->var->name;
                if ($isKnown($name)) {
                    $offenses[] = new Offense(
                        $this->name(),
                        $path,
                        (int) $node->var->getStartLine(),
                        1,
                        sprintf('Variable $%s shadows an existing variable in this scope.', $name)
                    );
                }

                $register($name);
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

        return $offenses;
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }
}
