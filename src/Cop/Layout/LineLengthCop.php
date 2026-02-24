<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Layout;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

final class LineLengthCop implements CopInterface
{
    public function name(): string
    {
        return 'Layout/LineLength';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $max = (int) ($config['Max'] ?? 120);
        $offenses = [];

        foreach ($file->lines() as $index => $line) {
            $length = mb_strlen($line);
            if ($length <= $max) {
                continue;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                $index + 1,
                $max + 1,
                sprintf('Line is too long. [%d/%d]', $length, $max)
            );
        }

        return $offenses;
    }
}
