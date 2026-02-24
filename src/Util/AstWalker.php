<?php

declare(strict_types=1);

namespace PHPuboCop\Util;

use PhpParser\Node;

final class AstWalker
{
    /** @param callable(Node): void $callback */
    public static function walk(array $nodes, callable $callback): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            $callback($node);

            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->{$subNodeName};

                if ($subNode instanceof Node) {
                    self::walk([$subNode], $callback);
                    continue;
                }

                if (is_array($subNode)) {
                    self::walk($subNode, $callback);
                }
            }
        }
    }
}
