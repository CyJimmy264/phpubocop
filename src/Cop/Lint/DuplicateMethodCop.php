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

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
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
            if ($this->handleNamespaceNode($node, $path, $offenses)) {
                continue;
            }
            if ($this->handleFunctionNode($node, $namespace, $path, $seen, $offenses)) {
                continue;
            }
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
                    sprintf('Duplicate method declaration: %s().', $method->name->toString()),
                );
                continue;
            }

            $seen[$name] = true;
        }
    }

    /** @param list<Offense> $offenses */
    private function handleNamespaceNode(Node $node, string $path, array &$offenses): bool
    {
        if (!$node instanceof Stmt\Namespace_) {
            return false;
        }

        $nextNamespace = $node->name?->toString() ?? '';
        $this->inspectDuplicateFunctions($node->stmts, $nextNamespace, $path, $offenses);
        return true;
    }

    private function handleFunctionNode(
        Node $node,
        string $namespace,
        string $path,
        array &$seen,
        array &$offenses,
    ): bool {
        if (!$node instanceof Stmt\Function_) {
            return false;
        }
        $name = $node->name->toString();
        $key = $this->functionKey($namespace, $name);
        if ($this->isDuplicateFunctionKey($seen, $key)) {
            $offenses[] = $this->duplicateFunctionOffense($path, (int) $node->getStartLine(), $name);
            return true;
        }
        $seen[$key] = true;
        return true;
    }

    private function functionKey(string $namespace, string $name): string
    {
        return strtolower(($namespace !== '' ? $namespace . '\\' : '') . $name);
    }

    /** @param array<string,bool> $seen */
    private function isDuplicateFunctionKey(array $seen, string $key): bool
    {
        return isset($seen[$key]);
    }

    private function duplicateFunctionOffense(string $path, int $line, string $name): Offense
    {
        return new Offense(
            $this->name(),
            $path,
            $line,
            1,
            sprintf('Duplicate function declaration: %s().', $name),
        );
    }
}
