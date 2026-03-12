<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;

final class ThinLayerForbiddenMethodCallsCop implements CopInterface
{
    use ThinLayerPathMatcher;

    /** @var list<string> */
    private array $patterns = [];

    public function name(): string
    {
        return 'Architecture/ThinLayerForbiddenMethodCalls';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $this->patterns = $this->methodPatterns($config['ForbiddenMethodPatterns'] ?? [
            '^(query|exec|fetch|fetchall|fetchassoc|fetchrow)$',
        ]);
        if ($this->patterns === []) {
            return [];
        }

        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            $methodName = $this->methodNameIfForbidden($node);
            if ($methodName === null) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                sprintf('Avoid direct method call "%s" in thin-layer scripts; delegate to lib/services.', $methodName),
                'warning',
            );
        });

        return $offenses;
    }

    private function methodNameIfForbidden(Node $node): ?string
    {
        if (!$node instanceof Expr\MethodCall && !$node instanceof Expr\NullsafeMethodCall) {
            return null;
        }
        if (!$node->name instanceof Identifier) {
            return null;
        }

        $methodName = strtolower($node->name->toString());
        foreach ($this->patterns as $pattern) {
            if ($this->matchesPattern($pattern, $methodName)) {
                return $methodName;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function methodPatterns(array $raw): array
    {
        $patterns = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $patterns[] = $item;
            }
        }

        return $patterns;
    }

    private function matchesPattern(string $pattern, string $value): bool
    {
        $regex = '/' . $pattern . '/i';
        $matched = $this->safePregMatch($regex, $value);
        return $matched === 1;
    }

    private function safePregMatch(string $regex, string $subject): int|false
    {
        set_error_handler(static fn (): bool => true);
        try {
            return preg_match($regex, $subject);
        } finally {
            restore_error_handler();
        }
    }
}
