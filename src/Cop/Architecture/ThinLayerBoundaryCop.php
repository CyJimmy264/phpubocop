<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\SourceFile;

final class ThinLayerBoundaryCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerBoundary';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        return [];
    }

}
