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
                $patterns[] = str_replace('\\', '/', $item);
            }
        }

        return $patterns;
    }

    /** @param list<string> $patterns */
    private function matchesAnyPath(string $path, array $patterns): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            if ($this->matchesPathPattern($normalizedPath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPathPattern(string $path, string $pattern): bool
    {
        $normalizedPattern = ltrim($pattern, '/');
        if ($this->matchesGlob($normalizedPattern, $path)) {
            return true;
        }

        foreach ($this->pathSuffixes($path) as $suffix) {
            if ($this->matchesGlob($normalizedPattern, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function pathSuffixes(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        $parts = explode('/', $trimmed);
        $suffixes = [];
        $count = count($parts);
        for ($i = 1; $i < $count; $i++) {
            $suffixes[] = implode('/', array_slice($parts, $i));
        }

        return $suffixes;
    }

    private function matchesGlob(string $pattern, string $path): bool
    {
        $regex = $this->globToRegex($pattern);
        return preg_match($regex, $path) === 1;
    }

    private function globToRegex(string $pattern): string
    {
        $quoted = preg_quote($pattern, '#');
        $quoted = str_replace('\*\*', '___DOUBLE_WILDCARD___', $quoted);
        $quoted = str_replace('\*', '[^/]*', $quoted);
        $quoted = str_replace('___DOUBLE_WILDCARD___', '.*', $quoted);

        return '#^' . $quoted . '$#';
    }
}
