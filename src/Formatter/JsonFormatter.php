<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

final class JsonFormatter implements FormatterInterface
{
    public function format(array $offenses, array $context = []): string
    {
        $payload = array_map(static fn ($offense) => $offense->toArray(), $offenses);
        return json_encode(['offenses' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
