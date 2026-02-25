<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class UnreachableCodeCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/UnreachableCode';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!property_exists($node, 'stmts') || !is_array($node->stmts)) {
                return;
            }

            $this->inspectStatementList($node->stmts, $file, $offenses);
        });

        return $offenses;
    }

    /** @param list<Offense> $offenses @param array<Stmt> $statements */
    private function inspectStatementList(array $statements, SourceFile $file, array &$offenses): void
    {
        $terminated = false;

        foreach ($statements as $statement) {
            if (!$statement instanceof Stmt) {
                continue;
            }

            if ($terminated) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $statement->getStartLine(),
                    1,
                    'Unreachable code detected.'
                );
                continue;
            }

            if ($this->isTerminatingStatement($statement)) {
                $terminated = true;
            }
        }
    }

    private function isTerminatingStatement(Stmt $statement): bool
    {
        return $statement instanceof Stmt\Return_
            || $statement instanceof Stmt\Throw_
            || $statement instanceof Stmt\Break_
            || $statement instanceof Stmt\Continue_
            || ($statement instanceof Stmt\Expression && ($statement->expr instanceof Expr\Exit_ || $statement->expr instanceof Expr\Throw_));
    }
}
