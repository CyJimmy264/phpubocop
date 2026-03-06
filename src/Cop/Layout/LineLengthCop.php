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
        return $this->collectLineLengthOffenses($file, $max);
    }

    /** @return list<Offense> */
    private function collectLineLengthOffenses(SourceFile $file, int $max): array
    {
        $offenses = [];
        foreach ($file->lines() as $index => $line) {
            $length = mb_strlen($line);
            if ($length > $max) {
                $offenses[] = $this->newOffense($file, $index + 1, $length, $max);
            }
        }

        return $offenses;
    }

    private function newOffense(SourceFile $file, int $line, int $length, int $max): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            $max + 1,
            sprintf('Line is too long. [%d/%d]', $length, $max),
        );
    }
}
