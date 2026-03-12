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

final class ThinLayerComplexityCop implements CopInterface
{
    use ThinLayerPathMatcher;

    /** @var list<class-string<Node>> */
    private const BRANCH_NODES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\Switch_::class,
        Stmt\Case_::class,
        Expr\Ternary::class,
    ];

    public function name(): string
    {
        return 'Architecture/ThinLayerComplexity';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $branchCount = 0;
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$branchCount): void {
            if ($this->isBranchNode($node)) {
                $branchCount++;
            }
        });

        $maxBranchNodes = (int) ($config['MaxBranchNodes'] ?? 6);
        if ($branchCount <= $maxBranchNodes) {
            return [];
        }

        return [
            new Offense(
                $this->name(),
                $file->path,
                1,
                1,
                sprintf('Thin-layer script is too complex. Branch nodes [%d/%d].', $branchCount, $maxBranchNodes),
                'warning',
            ),
        ];
    }

    private function isBranchNode(Node $node): bool
    {
        foreach (self::BRANCH_NODES as $branchNodeClass) {
            if ($node instanceof $branchNodeClass) {
                return true;
            }
        }

        return false;
    }
}
