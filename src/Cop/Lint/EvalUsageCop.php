<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;

final class EvalUsageCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/EvalUsage';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Eval_) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid using eval().',
                'warning',
            );
        });

        return $offenses;
    }
}
