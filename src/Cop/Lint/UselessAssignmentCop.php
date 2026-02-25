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
        $pendingAssignmentLine = [];

        $markRead = function (string $name) use (&$pendingAssignmentLine): void {
            unset($pendingAssignmentLine[$name]);
        };

        $markAssignment = function (string $name, int $line) use (&$pendingAssignmentLine, &$offenses, $path): void {
            if (isset($pendingAssignmentLine[$name])) {
                $offenses[] = new Offense(
                    $this->name(),
                    $path,
                    $pendingAssignmentLine[$name],
                    1,
                    sprintf('Useless assignment to $%s. Value is overwritten before being read.', $name)
                );
            }

            $pendingAssignmentLine[$name] = $line;
        };

        $visit = function (Node $node) use (&$visit, $scope, $markRead, $markAssignment): void {
            if ($node !== $scope && $this->isScope($node)) {
                return;
            }

            if ($node instanceof Expr\Assign) {
                $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $markAssignment);
                $visit($node->expr);
                return;
            }

            if ($node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
                $this->markReadVariable($node->var, $markRead);
                $this->markAssignedVariable($node->var, (int) $node->getStartLine(), $markAssignment);
                if ($node instanceof Expr\AssignOp) {
                    $visit($node->expr);
                } else {
                    $visit($node->expr);
                }
                return;
            }

            if ($node instanceof Expr\Variable && is_string($node->name)) {
                $markRead($node->name);
                return;
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
        return $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
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

    /** @param callable(string,int): void $markAssignment */
    private function markAssignedVariable(Node $target, int $line, callable $markAssignment): void
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            $markAssignment($target->name, $line);
        }
    }
}
