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
    private int $scopeStartLine = 0;
    private int $scopeEndLine = 0;
    /** @var array<int,int> */
    private array $significantLinePrefixSums = [];
    /** @var array<string,bool> */
    private array $countAsOneOptions = [];
    /** @var array<int,bool> */
    private array $foldedLines = [];
    /** @var array<int,bool> */
    private array $significantLines = [];

    public function name(): string
    {
        return 'Metrics/MethodLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 20);
        $countAsOne = $this->normalizedCountAsOne($config['CountAsOne'] ?? ['array', 'heredoc', 'call_chain']);
        $offenses = [];
        $this->significantLines = $this->significantLinesByLine($file);
        $this->significantLinePrefixSums = $this->buildPrefixSums($file, $this->significantLines);

        foreach ($file->astNodes() as $node) {
            if (!$this->isMeasuredScope($node)) {
                continue;
            }

            $this->appendOffenseForScopeIfNeeded($node, $file, $max, $countAsOne, $offenses);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function appendOffenseForScopeIfNeeded(
        Node $scope,
        SourceFile $file,
        int $max,
        array $countAsOne,
        array &$offenses,
    ): void {
        $length = $this->scopeLength($scope, $file, $countAsOne);
        if ($length <= $max) {
            return;
        }

        $offenses[] = new Offense(
            $this->name(),
            $file->path,
            (int) $scope->getStartLine(),
            1,
            sprintf('Method has too many lines. [%d/%d]', $length, $max),
        );
    }

    private function isMeasuredScope(Node $node): bool
    {
        return $node instanceof Stmt\ClassMethod
            || $node instanceof Stmt\Function_
            || $node instanceof Expr\Closure;
    }

    private function scopeLength(Node $node, SourceFile $file, array $countAsOne): int
    {
        $this->scopeStartLine = (int) $node->getStartLine();
        $this->scopeEndLine = (int) $node->getEndLine();
        if ($this->scopeEndLine < $this->scopeStartLine) {
            return 0;
        }

        $baseLength = $this->countSignificantLinesInScope();
        $this->countAsOneOptions = $countAsOne;
        if ($this->countAsOneOptions === [] || $baseLength === 0) {
            return $baseLength;
        }

        $this->foldedLines = [];
        $this->collectFoldedLines($node);
        return max(0, $baseLength - count($this->foldedLines));
    }

    private function countSignificantLinesInScope(): int
    {
        $beforeStart = $this->scopeStartLine > 1
            ? ($this->significantLinePrefixSums[$this->scopeStartLine - 1] ?? 0)
            : 0;

        return ($this->significantLinePrefixSums[$this->scopeEndLine] ?? 0) - $beforeStart;
    }

    private function ignoredScopeToken(int $tokenId): bool
    {
        return in_array($tokenId, [
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
            T_INLINE_HTML,
        ], true);
    }

    private function collectFoldedLines(Node $node): void
    {
        if ($this->shouldCountAsOne($node)) {
            $this->markFoldedLineRange($node);
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->collectFoldedLines($child);
        }
    }

    /** @return array<int,bool> */
    private function significantLinesByLine(SourceFile $file): array
    {
        $lines = [];
        foreach ($file->tokens() as $token) {
            if (!is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;
            if ($this->ignoredScopeToken($tokenId)) {
                continue;
            }

            $line = (int) $line;
            $lineCount = substr_count((string) $text, "\n");
            for ($offset = 0; $offset <= $lineCount; $offset++) {
                $lines[$line + $offset] = true;
            }
        }

        return $lines;
    }

    /** @param array<int,bool> $significantLines @return array<int,int> */
    private function buildPrefixSums(SourceFile $file, array $significantLines): array
    {
        $prefixSums = [];
        $running = 0;
        $lineCount = count($file->lines());
        for ($line = 1; $line <= $lineCount; $line++) {
            if (isset($significantLines[$line])) {
                $running++;
            }

            $prefixSums[$line] = $running;
        }

        return $prefixSums;
    }

    private function markFoldedLineRange(Node $node): void
    {
        $nodeStart = max((int) $node->getStartLine(), $this->scopeStartLine);
        $nodeEnd = min((int) $node->getEndLine(), $this->scopeEndLine);
        for ($line = $nodeStart + 1; $line <= $nodeEnd; $line++) {
            if (isset($this->significantLines[$line])) {
                $this->foldedLines[$line] = true;
            }
        }
    }

    private function shouldCountAsOne(Node $node): bool
    {
        return $this->isArrayNodeToFold($node)
            || $this->isHeredocNodeToFold($node)
            || $this->isCallChainNodeToFold($node);
    }

    private function isArrayNodeToFold(Node $node): bool
    {
        if (!($this->countAsOneOptions['array'] ?? false)) {
            return false;
        }

        return $node instanceof Expr\Array_;
    }

    private function isHeredocNodeToFold(Node $node): bool
    {
        if (!($this->countAsOneOptions['heredoc'] ?? false)) {
            return false;
        }

        if (!$node instanceof String_) {
            return false;
        }

        $kind = $node->getAttribute('kind');
        return $kind === String_::KIND_HEREDOC || $kind === String_::KIND_NOWDOC;
    }

    private function isCallChainNodeToFold(Node $node): bool
    {
        if (!($this->countAsOneOptions['call_chain'] ?? false)) {
            return false;
        }

        return $node instanceof Expr\MethodCall
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\FuncCall
            || $node instanceof Expr\NullsafeMethodCall;
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
