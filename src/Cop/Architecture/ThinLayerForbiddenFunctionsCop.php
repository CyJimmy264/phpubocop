<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

final class ThinLayerForbiddenFunctionsCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerForbiddenFunctions';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file, $config): void {
            if (!$this->isForbiddenFunctionCall($node, $config)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid forbidden function calls in thin-layer scripts; move logic to service layer.',
                'warning',
            );
        });

        return $offenses;
    }

    private function isForbiddenFunctionCall(Node $node, array $config): bool
    {
        if (!$node instanceof Expr\FuncCall || !$node->name instanceof Name) {
            return false;
        }

        $forbiddenFunctions = $this->normalizedStrings($config['ForbiddenFunctions'] ?? [
            'mysql_query',
            'mysqli_query',
            'pg_query',
        ]);

        return in_array(strtolower($node->name->toString()), $forbiddenFunctions, true);
    }
}
