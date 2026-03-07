<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

trait ThinLayerPathMatcher
{
    private function shouldCheckThinLayerFile(string $path, array $config): bool
    {
        $targetPaths = $this->pathPatterns($config['TargetPaths'] ?? ['**/*.php']);
        if ($targetPaths !== [] && !$this->matchesAnyPath($path, $targetPaths)) {
            return false;
        }

        $excludePaths = $this->pathPatterns($config['ExcludePaths'] ?? ['vendor/**']);
        if ($this->matchesAnyPath($path, $excludePaths)) {
            return false;
        }

        $businessLayerPaths = $this->pathPatterns($config['BusinessLayerPaths'] ?? []);
        return !$this->matchesAnyPath($path, $businessLayerPaths);
    }

    /** @return list<string> */
    private function normalizedStrings(array $raw): array
    {
        $result = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = strtolower($item);
            }
        }

        return $result;
    }

    /** @return list<string> */
    private function pathPatterns(array $raw): array
    {
        $patterns = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $patterns[] = str_replace('**', '*', str_replace('\\', '/', $item));
            }
        }

        return $patterns;
    }

    /** @param list<string> $patterns */
    private function matchesAnyPath(string $path, array $patterns): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $normalizedPath) || fnmatch('*/' . ltrim($pattern, '/'), $normalizedPath)) {
                return true;
            }
        }

        return false;
    }
}
