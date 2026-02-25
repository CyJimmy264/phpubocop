<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class BooleanLiteralComparisonCop implements CopInterface
{
    public function name(): string
    {
        return 'Style/BooleanLiteralComparison';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$this->isBooleanComparison($node)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid comparing to boolean literals; simplify the condition.'
            );
        });

        return $offenses;
    }

    private function isBooleanComparison(Node $node): bool
    {
        if (!$node instanceof Expr\BinaryOp\Identical
            && !$node instanceof Expr\BinaryOp\NotIdentical
            && !$node instanceof Expr\BinaryOp\Equal
            && !$node instanceof Expr\BinaryOp\NotEqual) {
            return false;
        }

        return $this->isBooleanLiteral($node->left) || $this->isBooleanLiteral($node->right);
    }

    private function isBooleanLiteral(Node $node): bool
    {
        return $node instanceof Expr\ConstFetch
            && in_array(strtolower($node->name->toString()), ['true', 'false'], true);
    }
}
