<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;

final class EmptyCatchCop implements CopInterface
{
    public function name(): string
    {
        return 'Style/EmptyCatch';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Catch_) {
                return;
            }

            if ($node->stmts !== []) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Empty catch block detected. Add handling, logging, or explicit comment.',
                'warning',
            );
        });

        return $offenses;
    }
}
