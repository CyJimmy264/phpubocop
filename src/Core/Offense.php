<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

final class Offense
{
    public readonly int $line;
    public readonly int $column;
    public readonly string $message;
    public readonly string $severity;

    public function __construct(
        public readonly string $copName,
        public readonly string $file,
        mixed ...$payload,
    ) {
        [$this->line, $this->column, $this->message, $this->severity] = $this->normalizePayload($payload);
    }

    public function toArray(): array
    {
        return [
            'cop' => $this->copName,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:int,1:int,2:string,3:string}
     */
    private function normalizePayload(array $payload): array
    {
        $positionPayload = $this->positionPayload($payload);
        if ($positionPayload !== null) {
            return $positionPayload;
        }

        $legacyPayload = $this->legacyPayload($payload);
        if ($legacyPayload !== null) {
            return $legacyPayload;
        }

        throw new \InvalidArgumentException('Invalid offense payload.');
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:int,1:int,2:string,3:string}|null
     */
    private function positionPayload(array $payload): ?array
    {
        if (!isset($payload[0], $payload[1])) {
            return null;
        }
        if (!$payload[0] instanceof SourcePosition || !is_string($payload[1])) {
            return null;
        }

        $severity = $payload[2] ?? 'convention';
        if (!is_string($severity)) {
            return null;
        }

        return [$payload[0]->line, $payload[0]->column, $payload[1], $severity];
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:int,1:int,2:string,3:string}|null
     */
    private function legacyPayload(array $payload): ?array
    {
        if (!isset($payload[0], $payload[1], $payload[2])) {
            return null;
        }
        if (!is_int($payload[0]) || !is_int($payload[1]) || !is_string($payload[2])) {
            return null;
        }

        $severity = $payload[3] ?? 'convention';
        if (!is_string($severity)) {
            return null;
        }

        return [$payload[0], $payload[1], $payload[2], $severity];
    }
}
