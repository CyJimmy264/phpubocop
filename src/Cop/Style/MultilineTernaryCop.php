<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;

final class MultilineTernaryCop implements CopInterface
{
    public function name(): string
    {
        return 'Style/MultilineTernary';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$this->isMultilineTernary($node)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid multiline ternary expressions; rewrite as if/else or keep ternary on one line.',
            );
        });

        return $offenses;
    }

    private function isMultilineTernary(Node $node): bool
    {
        return $node instanceof Expr\Ternary
            && (int) $node->getEndLine() > (int) $node->getStartLine();
    }
}
