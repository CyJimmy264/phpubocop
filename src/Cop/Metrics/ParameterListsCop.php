<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

final class ParameterListsCop implements CopInterface
{
    public function name(): string
    {
        return 'Metrics/ParameterLists';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 5);
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
            $count = $this->parameterCount($node);
            if ($count > $max) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    sprintf('Method has too many parameters. [%d/%d]', $count, $max)
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
        return $node instanceof Stmt\ClassMethod
            || $node instanceof Stmt\Function_
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }

    private function parameterCount(FunctionLike $node): int
    {
        return count($node->getParams());
    }
}
