<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class ThinLayerGlobalStateUsageCop implements CopInterface
{
    use ThinLayerPathMatcher;

    /** @var list<string> */
    private array $forbiddenGlobals = [];

    public function name(): string
    {
        return 'Architecture/ThinLayerGlobalStateUsage';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $this->forbiddenGlobals = $this->forbiddenGlobals($config['ForbiddenGlobals'] ?? [
            'GLOBALS',
            'APPLICATION',
            'USER',
            'DB',
        ]);
        $checkGlobalKeyword = (bool) ($config['CheckGlobalKeyword'] ?? true);

        return $this->collectOffenses($file, $checkGlobalKeyword);
    }

    /** @return list<Offense> */
    private function collectOffenses(SourceFile $file, bool $checkGlobalKeyword): array
    {
        $offenses = [];
        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file, $checkGlobalKeyword): void {
            $offense = $this->offenseForNode($node, $file, $checkGlobalKeyword);
            if ($offense !== null) {
                $offenses[] = $offense;
            }
        });

        return $offenses;
    }

    private function offenseForNode(Node $node, SourceFile $file, bool $checkGlobalKeyword): ?Offense
    {
        if ($checkGlobalKeyword && $node instanceof Stmt\Global_) {
            return $this->globalKeywordOffense($file, (int) $node->getStartLine());
        }
        if (!$node instanceof Expr\Variable || !is_string($node->name)) {
            return null;
        }

        $name = strtoupper($node->name);
        if (!in_array($name, $this->forbiddenGlobals, true)) {
            return null;
        }

        return $this->globalVariableOffense($file, (int) $node->getStartLine(), $name);
    }

    private function globalKeywordOffense(SourceFile $file, int $line): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            1,
            'Avoid global keyword in thin-layer scripts; pass dependencies explicitly.',
            'warning',
        );
    }

    private function globalVariableOffense(SourceFile $file, int $line, string $name): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            1,
            sprintf('Avoid direct use of $%s in thin-layer scripts; use explicit dependencies/services.', $name),
            'warning',
        );
    }

    /** @return list<string> */
    private function forbiddenGlobals(array $raw): array
    {
        $result = [];
        foreach ($raw as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }

            $result[] = strtoupper(ltrim($item, '$'));
        }

        return $result;
    }
}
