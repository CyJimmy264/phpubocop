<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class MethodLengthCop implements CopInterface
{
    public function name(): string
    {
        return 'Metrics/MethodLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 20);
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
            $length = $this->scopeLength($node);
            if ($length > $max) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    sprintf('Method has too many lines. [%d/%d]', $length, $max)
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
            || $node instanceof Expr\Closure;
    }

    private function scopeLength(Node $node): int
    {
        $start = (int) $node->getStartLine();
        $end = (int) $node->getEndLine();

        if ($end < $start) {
            return 0;
        }

        return $end - $start + 1;
    }
}
