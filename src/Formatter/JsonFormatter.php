<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

final class JsonFormatter implements FormatterInterface
{
    public function format(array $offenses, array $context = []): string
    {
        $payload = array_map(static fn ($offense) => $offense->toArray(), $offenses);
        $summary = $this->summary($offenses);

        if (isset($context['inspected_files']) && is_array($context['inspected_files'])) {
            $summary['inspected_file_count'] = count($context['inspected_files']);
        }

        return json_encode(
            ['summary' => $summary, 'offenses' => $payload],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
    }

    /** @param list<Offense> $offenses @return array<string,int> */
    private function summary(array $offenses): array
    {
        return [
            'offense_count' => count($offenses),
            'correctable_count' => $this->correctableCount($offenses),
            'offending_file_count' => $this->offendingFileCount($offenses),
        ];
    }

    /** @param list<Offense> $offenses */
    private function correctableCount(array $offenses): int
    {
        return count(array_filter($offenses, static fn (Offense $offense): bool => $offense->correctable));
    }

    /** @param list<Offense> $offenses */
    private function offendingFileCount(array $offenses): int
    {
        return count(array_unique(array_map(static fn (Offense $offense): string => $offense->file, $offenses)));
    }
}
