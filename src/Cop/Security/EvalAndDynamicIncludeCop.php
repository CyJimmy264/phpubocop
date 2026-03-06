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
    public function name(): string
    {
        return 'Security/EvalAndDynamicInclude';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $allowedPatterns = $this->allowedDynamicIncludePatterns($config['AllowedDynamicIncludePatterns'] ?? []);
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file, $allowedPatterns): void {
            if ($node instanceof Expr\Eval_) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Avoid eval(). It can execute untrusted input.',
                    'warning',
                );

                return;
            }

            if (!$node instanceof Expr\Include_) {
                return;
            }

            if ($this->isStaticIncludePath($node->expr)) {
                return;
            }

            if ($this->isAllowedDynamicIncludePath($file, $node->expr, $allowedPatterns)) {
                return;
            }

            $kind = match ($node->type) {
                Expr\Include_::TYPE_REQUIRE => 'require',
                Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
                Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
                default => 'include',
            };

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                sprintf('Avoid dynamic %s paths. Use fixed, validated paths.', $kind),
                'warning',
            );
        });

        return $offenses;
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
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }
            $patterns[] = $pattern;
        }

        return $patterns;
    }

    /** @param list<string> $patterns */
    private function isAllowedDynamicIncludePath(SourceFile $file, Node $expr, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        $start = $expr->getStartFilePos();
        $end = $expr->getEndFilePos();
        if (!is_int($start) || !is_int($end) || $end < $start) {
            return false;
        }

        $source = substr($file->content, $start, $end - $start + 1);
        if (!is_string($source) || $source === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (@preg_match('/' . $pattern . '/u', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
