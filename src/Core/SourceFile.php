<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;

final class SourceFile
{
    private ?array $ast = null;

    public function __construct(
        public readonly string $path,
        public readonly string $content
    ) {
    }

    public function lines(): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $this->content);
        return explode("\n", $normalized);
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
}
