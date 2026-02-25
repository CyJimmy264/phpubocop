<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class StrictComparisonCop implements CopInterface
{
    public function name(): string
    {
        return 'Style/StrictComparison';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if ($node instanceof Expr\BinaryOp\Equal) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Prefer strict comparison (===) over ==.'
                );
                return;
            }

            if ($node instanceof Expr\BinaryOp\NotEqual) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Prefer strict comparison (!==) over !=.'
                );
            }
        });

        return $offenses;
    }
}
