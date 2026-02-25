<?php

declare(strict_types=1);

namespace PHPuboCop\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    public function load(?string $configPath): array
    {
        if ($configPath === null || !is_file($configPath)) {
            return $this->defaultConfig();
        }

        $parsed = Yaml::parseFile($configPath);
        if (!is_array($parsed)) {
            return $this->defaultConfig();
        }

        return array_replace_recursive($this->defaultConfig(), $parsed);
    }

    private function defaultConfig(): array
    {
        return [
            'AllCops' => [
                'EnabledByDefault' => true,
                'Exclude' => ['vendor/**'],
                'Include' => ['**/*.php'],
            ],
            'Layout/LineLength' => [
                'Enabled' => true,
                'Max' => 120,
            ],
            'Layout/TrailingWhitespace' => [
                'Enabled' => true,
            ],
            'Lint/DuplicateArrayKey' => [
                'Enabled' => true,
            ],
            'Lint/EvalUsage' => [
                'Enabled' => true,
            ],
            'Lint/SuppressedError' => [
                'Enabled' => true,
            ],
            'Lint/ShadowingVariable' => [
                'Enabled' => true,
            ],
            'Lint/UnreachableCode' => [
                'Enabled' => true,
            ],
            'Lint/UselessAssignment' => [
                'Enabled' => true,
            ],
            'Lint/UnusedVariable' => [
                'Enabled' => true,
                'IgnorePrefixedUnderscore' => true,
                'IgnoreParameters' => true,
            ],
            'Metrics/AbcSize' => [
                'Enabled' => true,
                'Max' => 17,
            ],
            'Metrics/CyclomaticComplexity' => [
                'Enabled' => true,
                'Max' => 7,
            ],
            'Metrics/MethodLength' => [
                'Enabled' => true,
                'Max' => 20,
                'CountAsOne' => ['array', 'heredoc', 'call_chain'],
            ],
            'Metrics/PerceivedComplexity' => [
                'Enabled' => true,
                'Max' => 8,
            ],
            'Metrics/ParameterLists' => [
                'Enabled' => true,
                'Max' => 5,
            ],
            'Security/Unserialize' => [
                'Enabled' => true,
            ],
            'Security/Exec' => [
                'Enabled' => true,
            ],
            'Style/DoubleQuotes' => [
                'Enabled' => true,
            ],
            'Style/EmptyCatch' => [
                'Enabled' => true,
            ],
        ];
    }
}
