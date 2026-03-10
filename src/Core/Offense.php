<?php

declare(strict_types=1);

namespace PHPuboCop\Core;

final class Offense
{
    public readonly int $line;
    public readonly int $column;
    public readonly string $message;
    public readonly string $severity;
    public readonly bool $correctable;
    public readonly bool $safeAutocorrect;

    public function __construct(
        public readonly string $copName,
        public readonly string $file,
        mixed ...$payload,
    ) {
        [
            $this->line,
            $this->column,
            $this->message,
            $this->severity,
            $this->correctable,
            $this->safeAutocorrect,
        ] = $this->normalizePayload($payload);
    }

    public function withAutocorrect(bool $correctable, bool $safeAutocorrect): self
    {
        return new self(
            $this->copName,
            $this->file,
            $this->line,
            $this->column,
            $this->message,
            $this->severity,
            $correctable,
            $safeAutocorrect,
        );
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
            'correctable' => $this->correctable,
            'safe_autocorrect' => $this->safeAutocorrect,
        ];
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:int,1:int,2:string,3:string,4:bool,5:bool}
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
     * @return array{0:int,1:int,2:string,3:string,4:bool,5:bool}|null
     */
    private function positionPayload(array $payload): ?array
    {
        if (!isset($payload[0], $payload[1])) {
            return null;
        }
        if (!$payload[0] instanceof SourcePosition || !is_string($payload[1])) {
            return null;
        }

        $metadata = $this->payloadMetadata($payload, 2, 3, 4);
        if ($metadata === null) {
            return null;
        }

        return [$payload[0]->line, $payload[0]->column, $payload[1], ...$metadata];
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:int,1:int,2:string,3:string,4:bool,5:bool}|null
     */
    private function legacyPayload(array $payload): ?array
    {
        if (!isset($payload[0], $payload[1], $payload[2])) {
            return null;
        }
        if (!is_int($payload[0]) || !is_int($payload[1]) || !is_string($payload[2])) {
            return null;
        }

        $metadata = $this->payloadMetadata($payload, 3, 4, 5);
        if ($metadata === null) {
            return null;
        }

        return [$payload[0], $payload[1], $payload[2], ...$metadata];
    }

    /**
     * @param array<int,mixed> $payload
     * @return array{0:string,1:bool,2:bool}|null
     */
    private function payloadMetadata(
        array $payload,
        int $severityIndex,
        int $correctableIndex,
        int $safeAutocorrectIndex,
    ): ?array {
        $severity = $payload[$severityIndex] ?? 'convention';
        $correctable = $payload[$correctableIndex] ?? false;
        $safeAutocorrect = $payload[$safeAutocorrectIndex] ?? false;

        if (!is_string($severity) || !is_bool($correctable) || !is_bool($safeAutocorrect)) {
            return null;
        }

        return [$severity, $correctable, $safeAutocorrect];
    }
}
