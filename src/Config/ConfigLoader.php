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
            'Lint/EvalUsage' => [
                'Enabled' => true,
            ],
            'Metrics/AbcSize' => [
                'Enabled' => true,
                'Max' => 17,
            ],
            'Metrics/CyclomaticComplexity' => [
                'Enabled' => true,
                'Max' => 7,
            ],
            'Metrics/PerceivedComplexity' => [
                'Enabled' => true,
                'Max' => 8,
            ],
            'Style/DoubleQuotes' => [
                'Enabled' => true,
            ],
        ];
    }
}
