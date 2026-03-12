<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class ThinLayerSuperglobalUsageCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerSuperglobalUsage';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $forbidden = $this->forbiddenSuperglobals($config);
        if ($forbidden === []) {
            return [];
        }

        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file, $forbidden): void {
            if (!$node instanceof Expr\Variable || !is_string($node->name)) {
                return;
            }

            $name = strtoupper($node->name);
            if (!in_array($name, $forbidden, true)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                sprintf('Avoid direct use of $%s in thin-layer scripts; map input explicitly.', $name),
                'warning',
            );
        });

        return $offenses;
    }

    /** @return list<string> */
    private function forbiddenSuperglobals(array $config): array
    {
        $items = $config['ForbiddenSuperglobals'] ?? ['_REQUEST'];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }

            $normalized = ltrim($item, '$');
            $result[] = strtoupper($normalized);
        }

        return $result;
    }
}
