<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class ThinLayerSizeCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerSize';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $maxLines = (int) ($config['MaxLines'] ?? 200);
        $lineCount = $this->lineCount($file->content);
        if ($lineCount <= $maxLines) {
            return [];
        }

        return [
            new Offense(
                $this->name(),
                $file->path,
                1,
                1,
                sprintf('Thin-layer script is too large. Lines [%d/%d].', $lineCount, $maxLines),
                'warning',
            ),
        ];
    }

    private function lineCount(string $contents): int
    {
        if ($contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + 1;
    }
}
