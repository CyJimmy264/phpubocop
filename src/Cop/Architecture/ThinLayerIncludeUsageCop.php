<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class ThinLayerIncludeUsageCop implements CopInterface
{
    use ThinLayerPathMatcher;

    /** @var list<string> */
    private array $allowedPatterns = [];

    public function name(): string
    {
        return 'Architecture/ThinLayerIncludeUsage';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $this->allowedPatterns = $this->allowedPatterns($config['AllowedIncludePatterns'] ?? [
            '/bitrix/modules/main/include/prolog_before.php',
            '/bitrix/modules/main/include/prolog_after.php',
            '/bitrix/modules/main/include/prolog_admin_before.php',
            '/bitrix/modules/main/include/prolog_admin_after.php',
            '/bitrix/modules/main/include/epilog_admin.php',
            '/bitrix/header.php',
            '/bitrix/footer.php',
            '/local/php_interface/lib/',
            '/include/',
        ]);

        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Expr\Include_) {
                return;
            }

            if ($this->isAllowedInclude($file, $node)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                sprintf(
                    'Avoid %s in thin-layer scripts unless it matches AllowedIncludePatterns.',
                    $this->includeKind($node),
                ),
                'warning',
            );
        });

        return $offenses;
    }

    /** @return list<string> */
    private function allowedPatterns(array $raw): array
    {
        $patterns = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $patterns[] = $item;
            }
        }

        return $patterns;
    }

    private function isAllowedInclude(SourceFile $file, Expr\Include_ $node): bool
    {
        if ($this->allowedPatterns === []) {
            return false;
        }

        $source = $this->sourceSnippetForExpr($file, $node->expr);
        if ($source === null) {
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

    private function includeKind(Expr\Include_ $node): string
    {
        return match ($node->type) {
            Expr\Include_::TYPE_REQUIRE => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            default => 'include',
        };
    }

    private function matchesPattern(string $pattern, string $source): bool
    {
        $regex = '/' . preg_quote($pattern, '/') . '/i';
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
