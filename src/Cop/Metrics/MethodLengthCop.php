<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Metrics;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
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
        $countAsOne = $this->normalizedCountAsOne($config['CountAsOne'] ?? ['array', 'heredoc', 'call_chain']);
        $offenses = [];

        foreach ($file->ast() as $node) {
            $this->collectOffenses($node, $file, $max, $countAsOne, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectOffenses(Node $node, SourceFile $file, int $max, array $countAsOne, array &$offenses): void
    {
        if ($this->isMeasuredScope($node)) {
            $length = $this->scopeLength($node, $countAsOne);
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
                $this->collectOffenses($subNode, $file, $max, $countAsOne, $offenses);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->collectOffenses($child, $file, $max, $countAsOne, $offenses);
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

    private function scopeLength(Node $node, array $countAsOne): int
    {
        $start = (int) $node->getStartLine();
        $end = (int) $node->getEndLine();

        if ($end < $start) {
            return 0;
        }

        $baseLength = $end - $start + 1;
        if ($countAsOne === []) {
            return $baseLength;
        }

        $foldedLines = [];
        $visit = function (Node $current) use (&$visit, &$foldedLines, $countAsOne, $start, $end): void {
            if ($this->shouldCountAsOne($current, $countAsOne)) {
                $nodeStart = max((int) $current->getStartLine(), $start);
                $nodeEnd = min((int) $current->getEndLine(), $end);
                for ($line = $nodeStart + 1; $line <= $nodeEnd; $line++) {
                    $foldedLines[$line] = true;
                }
            }

            foreach ($current->getSubNodeNames() as $subNodeName) {
                $subNode = $current->{$subNodeName};
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

        $visit($node);
        return max(0, $baseLength - count($foldedLines));
    }

    private function shouldCountAsOne(Node $node, array $countAsOne): bool
    {
        if ($countAsOne['array'] ?? false) {
            if ($node instanceof Expr\Array_) {
                return true;
            }
        }

        if (($countAsOne['heredoc'] ?? false) && $node instanceof String_) {
            $kind = $node->getAttribute('kind');
            if ($kind === String_::KIND_HEREDOC || $kind === String_::KIND_NOWDOC) {
                return true;
            }
        }

        if (($countAsOne['call_chain'] ?? false)
            && ($node instanceof Expr\MethodCall
                || $node instanceof Expr\StaticCall
                || $node instanceof Expr\FuncCall
                || $node instanceof Expr\NullsafeMethodCall)) {
            return true;
        }

        return false;
    }

    private function normalizedCountAsOne(array $raw): array
    {
        $normalized = [];
        $aliases = [
            'hash' => 'array',
            'method_call' => 'call_chain',
            'call' => 'call_chain',
        ];

        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }

            $key = strtolower($item);
            $normalized[$aliases[$key] ?? $key] = true;
        }

        return $normalized;
    }
}
