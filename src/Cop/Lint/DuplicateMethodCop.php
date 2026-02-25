<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Stmt;

final class DuplicateMethodCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/DuplicateMethod';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        $this->inspectDuplicateFunctions($file->ast(), '', $file->path, $offenses);

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Stmt\ClassLike) {
                return;
            }

            $this->inspectDuplicateMethodsInClassLike($node, $file->path, $offenses);
        });

        return $offenses;
    }

    /** @param list<Offense> $offenses @param array<Node> $nodes */
    private function inspectDuplicateFunctions(array $nodes, string $namespace, string $path, array &$offenses): void
    {
        $seen = [];

        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }

            if ($node instanceof Stmt\Namespace_) {
                $nextNamespace = $node->name?->toString() ?? '';
                $this->inspectDuplicateFunctions($node->stmts, $nextNamespace, $path, $offenses);
                continue;
            }

            if (!$node instanceof Stmt\Function_) {
                continue;
            }

            $key = strtolower(($namespace !== '' ? $namespace . '\\' : '') . $node->name->toString());
            if (isset($seen[$key])) {
                $offenses[] = new Offense(
                    $this->name(),
                    $path,
                    (int) $node->getStartLine(),
                    1,
                    sprintf('Duplicate function declaration: %s().', $node->name->toString())
                );
                continue;
            }

            $seen[$key] = true;
        }
    }

    /** @param list<Offense> $offenses */
    private function inspectDuplicateMethodsInClassLike(Stmt\ClassLike $classLike, string $path, array &$offenses): void
    {
        $seen = [];

        foreach ($classLike->getMethods() as $method) {
            $name = strtolower($method->name->toString());
            if (isset($seen[$name])) {
                $offenses[] = new Offense(
                    $this->name(),
                    $path,
                    (int) $method->getStartLine(),
                    1,
                    sprintf('Duplicate method declaration: %s().', $method->name->toString())
                );
                continue;
            }

            $seen[$name] = true;
        }
    }
}
