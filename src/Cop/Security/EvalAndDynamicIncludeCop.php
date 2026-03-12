<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Security;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;

final class EvalAndDynamicIncludeCop implements CopInterface
{
    /** @var list<string> */
    private array $allowedPatterns = [];

    public function name(): string
    {
        return 'Security/EvalAndDynamicInclude';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $this->allowedPatterns = $this->allowedDynamicIncludePatterns(
            $config['AllowedDynamicIncludePatterns'] ?? [],
        );
        $offenses = [];

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            if ($this->handleEvalNode($node, $file, $offenses)) {
                return;
            }
            if (!$this->isDynamicIncludeNode($node)) {
                return;
            }

            if ($this->isStaticIncludePath($node->expr)) {
                return;
            }
            if ($this->isAllowedDynamicIncludePath($file, $node->expr)) {
                return;
            }

            $offenses[] = $this->dynamicIncludeOffense($file, $node);
        });

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function handleEvalNode(Node $node, SourceFile $file, array &$offenses): bool
    {
        if (!$node instanceof Expr\Eval_) {
            return false;
        }

        $offenses[] = new Offense(
            $this->name(),
            $file->path,
            (int) $node->getStartLine(),
            1,
            'Avoid eval(). It can execute untrusted input.',
            'warning',
        );

        return true;
    }

    private function isDynamicIncludeNode(Node $node): bool
    {
        return $node instanceof Expr\Include_;
    }

    private function dynamicIncludeOffense(SourceFile $file, Expr\Include_ $node): Offense
    {
        $kind = $this->includeKind($node);
        return new Offense(
            $this->name(),
            $file->path,
            (int) $node->getStartLine(),
            1,
            sprintf('Avoid dynamic %s paths. Use fixed, validated paths.', $kind),
            'warning',
        );
    }

    private function includeKind(Expr\Include_ $node): string
    {
        return match ($node->type) {
            Expr\Include_::TYPE_REQUIRE => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            default => 'include',
        };
    }

    private function isStaticIncludePath(Node $expr): bool
    {
        if ($expr instanceof String_) {
            return true;
        }

        if ($expr instanceof Node\Scalar\MagicConst\Dir
            || $expr instanceof Node\Scalar\MagicConst\File
            || $expr instanceof Node\Scalar\MagicConst\Namespace_) {
            return true;
        }

        if ($expr instanceof Expr\BinaryOp\Concat) {
            return $this->isStaticIncludePath($expr->left) && $this->isStaticIncludePath($expr->right);
        }

        return false;
    }

    /** @return list<string> */
    private function allowedDynamicIncludePatterns(array $raw): array
    {
        $patterns = [];
        foreach ($raw as $pattern) {
            $normalized = $this->normalizePattern($pattern);
            if ($normalized === null) {
                continue;
            }

            $patterns[] = $normalized;
        }

        return $patterns;
    }

    private function normalizePattern(mixed $pattern): ?string
    {
        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        return $pattern;
    }

    private function isAllowedDynamicIncludePath(SourceFile $file, Node $expr): bool
    {
        $source = $this->sourceSnippetForExpr($file, $expr);
        if ($source === null || $this->allowedPatterns === []) {
            return false;
        }

        foreach ($this->allowedPatterns as $pattern) {
            if ($this->matchesPattern($pattern, $source)) {
                return true;
            }
        }

        return false;
    }

    private function sourceSnippetForExpr(SourceFile $file, Node $expr): ?string
    {
        $start = $expr->getStartFilePos();
        $end = $expr->getEndFilePos();
        if (!is_int($start) || !is_int($end) || $end < $start) {
            return null;
        }

        $source = substr($file->content, $start, $end - $start + 1);
        if (!is_string($source) || $source === '') {
            return null;
        }

        return $source;
    }

    private function matchesPattern(string $pattern, string $source): bool
    {
        $regex = '/' . $pattern . '/u';
        $matched = $this->safePregMatch($regex, $source);
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
