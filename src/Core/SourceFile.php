<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;

final class SourceFile
{
    private ?array $ast = null;
    private ?array $astNodes = null;
    private ?array $lines = null;
    private ?array $tokens = null;

    public function __construct(
        public readonly string $path,
        public readonly string $content
    ) {
    }

    public function lines(): array
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $this->content);
        $this->lines = explode("\n", $normalized);
        return $this->lines;
    }

    /** @return list<string|array{int,string,int}> */
    public function tokens(): array
    {
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        $this->tokens = token_get_all($this->content);
        return $this->tokens;
    }

    /** @return array<Node> */
    public function ast(): array
    {
        if ($this->ast !== null) {
            return $this->ast;
        }

        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $this->ast = $parser->parse($this->content) ?? [];
        } catch (Error) {
            $this->ast = [];
        }

        return $this->ast;
    }

    /** @return list<Node> */
    public function astNodes(): array
    {
        if ($this->astNodes !== null) {
            return $this->astNodes;
        }

        $this->astNodes = $this->flattenAstNodes();
        return $this->astNodes;
    }

    /** @return list<Node> */
    private function flattenAstNodes(): array
    {
        $nodes = [];
        $stack = array_reverse($this->ast());
        while ($stack !== []) {
            $node = array_pop($stack);
            if (!$node instanceof Node) {
                continue;
            }

            $nodes[] = $node;
            $this->pushChildrenOntoStack($stack, $this->childNodes($node));
        }

        return $nodes;
    }

    /** @param list<Node> $stack @param list<Node> $children */
    private function pushChildrenOntoStack(array &$stack, array $children): void
    {
        for ($i = count($children) - 1; $i >= 0; $i--) {
            $stack[] = $children[$i];
        }
    }

    /** @return list<Node> */
    private function childNodes(Node $node): array
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
