<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;

final class ThinLayerLengthCop implements CopInterface
{
    use ThinLayerPathMatcher;

    /** @var array<string,bool> */
    private array $countAsOneOptions = [];
    /** @var array<int,bool> */
    private array $foldedLines = [];

    public function name(): string
    {
        return 'Architecture/ThinLayerLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $maxLines = (int) ($config['Max'] ?? 25);
        $countAsOne = $this->normalizedCountAsOne($config['CountAsOne'] ?? ['array', 'heredoc', 'call_chain']);
        $lineCount = $this->lineCount($file, $countAsOne);
        if ($lineCount <= $maxLines) {
            return [];
        }

        return [
            new Offense(
                $this->name(),
                $file->path,
                1,
                1,
                sprintf('Thin-layer script is too large. Lines [%d/%d].', $lineCount, $maxLines),
                'warning',
            ),
        ];
    }

    private function lineCount(SourceFile $file, array $countAsOne): int
    {
        if ($file->content === '') {
            return 0;
        }

        $significantLines = $this->significantLines($file->tokens());
        if ($significantLines === []) {
            return 0;
        }

        $this->countAsOneOptions = $countAsOne;
        if ($this->countAsOneOptions === []) {
            return count($significantLines);
        }

        return max(0, count($significantLines) - $this->foldedLineCount($file, $significantLines));
    }

    /** @param array<int,bool> $significantLines */
    private function foldedLineCount(SourceFile $file, array $significantLines): int
    {
        $this->foldedLines = [];
        foreach ($file->ast() as $node) {
            $this->collectFoldedLines($node, $significantLines);
        }

        return count($this->foldedLines);
    }

    /** @param list<string|array{int,string,int}> $tokens */
    private function significantLines(array $tokens): array
    {
        $significantLines = [];
        $currentLine = 1;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $currentLine = $this->handleArrayToken($token, $significantLines);
                continue;
            }

            $currentLine = $this->handleStringToken($token, $currentLine, $significantLines);
        }

        return $significantLines;
    }

    /** @param array{int,string,int} $token @param array<int, bool> $significantLines */
    private function handleArrayToken(array $token, array &$significantLines): int
    {
        [$tokenId, $text, $line] = $token;
        $line = (int) $line;
        $text = (string) $text;

        if ($this->isSignificantToken((int) $tokenId)) {
            $this->markLines($significantLines, $line, $text);
        }

        return $line + substr_count($text, "\n");
    }

    /** @param array<int, bool> $significantLines */
    private function handleStringToken(string $token, int $currentLine, array &$significantLines): int
    {
        if (trim($token) !== '') {
            $significantLines[$currentLine] = true;
        }

        return $currentLine + substr_count($token, "\n");
    }

    private function isSignificantToken(int $tokenId): bool
    {
        return !in_array($tokenId, [
            T_INLINE_HTML,
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_CLOSE_TAG,
        ], true);
    }

    /** @param array<int, bool> $lines */
    private function markLines(array &$lines, int $startLine, string $text): void
    {
        $lineCount = substr_count($text, "\n");
        for ($offset = 0; $offset <= $lineCount; $offset++) {
            $lines[$startLine + $offset] = true;
        }
    }

    /** @param array<int,bool> $significantLines */
    private function collectFoldedLines(Node $node, array $significantLines): void
    {
        if ($this->shouldCountAsOne($node)) {
            $this->markFoldedLineRange($node, $significantLines);
        }

        foreach ($this->childNodesOf($node) as $child) {
            $this->collectFoldedLines($child, $significantLines);
        }
    }

    /** @param array<int,bool> $significantLines */
    private function markFoldedLineRange(Node $node, array $significantLines): void
    {
        $nodeStart = (int) $node->getStartLine();
        $nodeEnd = (int) $node->getEndLine();
        for ($line = $nodeStart + 1; $line <= $nodeEnd; $line++) {
            if (isset($significantLines[$line])) {
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
        return ($this->countAsOneOptions['array'] ?? false) && $node instanceof Expr\Array_;
    }

    private function isHeredocNodeToFold(Node $node): bool
    {
        if (!($this->countAsOneOptions['heredoc'] ?? false) || !$node instanceof String_) {
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
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $children[] = $child;
                    }
                }
            }
        }

        return $children;
    }
}
