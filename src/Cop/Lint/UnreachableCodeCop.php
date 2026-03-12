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
        return $this->collectUnreachableOffenses($file);
    }

    /** @param list<Offense> $offenses @param array<Stmt> $statements */
    private function inspectStatementList(array $statements, SourceFile $file, array &$offenses): void
    {
        $terminated = false;

        foreach ($statements as $statement) {
            if (!$statement instanceof Stmt || !$this->shouldProcessStatement($statement)) {
                continue;
            }

            if ($terminated) {
                $offenses[] = $this->unreachableOffense($file, $statement);
            } elseif ($this->isTerminatingStatement($statement)) {
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
            || $this->isTerminatingExpressionStatement($statement);
    }

    /** @return list<Offense> */
    private function collectUnreachableOffenses(SourceFile $file): array
    {
        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            $statements = $this->statementListFromNode($node);
            if ($statements !== null) {
                $this->inspectStatementList($statements, $file, $offenses);
            }
        });

        return $offenses;
    }

    /** @return array<Stmt>|null */
    private function statementListFromNode(Node $node): ?array
    {
        if (!property_exists($node, 'stmts') || !is_array($node->stmts)) {
            return null;
        }

        return $node->stmts;
    }

    private function isTerminatingExpressionStatement(Stmt $statement): bool
    {
        if (!$statement instanceof Stmt\Expression) {
            return false;
        }

        return $statement->expr instanceof Expr\Exit_
            || $statement->expr instanceof Expr\Throw_;
    }

    private function shouldProcessStatement(Stmt $statement): bool
    {
        return true;
    }

    private function unreachableOffense(SourceFile $file, Stmt $statement): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            (int) $statement->getStartLine(),
            1,
            'Unreachable code detected.',
        );
    }
}
