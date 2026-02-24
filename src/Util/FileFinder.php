<?php

declare(strict_types=1);

namespace PHPuboCop\Util;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileFinder
{
    /** @return list<string> */
    public function find(string $path, array $config): array
    {
        if (is_file($path)) {
            return [$path];
        }

        $files = [];
        $exclude = $config['AllCops']['Exclude'] ?? [];
        $gitignoreRules = $this->loadGitignoreRules($path);
        $root = rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            if (!str_ends_with($filePath, '.php')) {
                continue;
            }

            if ($this->isExcluded($filePath, $exclude)) {
                continue;
            }

            if ($this->isIgnoredByGitignore($filePath, $root, $gitignoreRules)) {
                continue;
            }

            $files[] = $filePath;
        }

        sort($files);
        return $files;
    }

    private function isExcluded(string $path, array $excludePatterns): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($excludePatterns as $pattern) {
            $pattern = str_replace('**', '*', (string) $pattern);
            if (fnmatch($pattern, $normalized) || fnmatch('*/' . ltrim($pattern, '/'), $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{pattern: string, negated: bool}>
     */
    private function loadGitignoreRules(string $rootPath): array
    {
        $gitignorePath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_file($gitignorePath)) {
            return [];
        }

        $rules = [];
        foreach (file($gitignorePath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $rule = trim($line);
            if ($rule === '' || str_starts_with($rule, '#')) {
                continue;
            }

            $negated = str_starts_with($rule, '!');
            if ($negated) {
                $rule = substr($rule, 1);
            }

            if ($rule === '') {
                continue;
            }

            $rules[] = [
                'pattern' => $rule,
                'negated' => $negated,
            ];
        }

        return $rules;
    }

    /**
     * @param list<array{pattern: string, negated: bool}> $rules
     */
    private function isIgnoredByGitignore(string $path, string $root, array $rules): bool
    {
        if ($rules === []) {
            return false;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $relativePath = ltrim(substr($normalizedPath, strlen($root)), '/');
        if ($relativePath === '') {
            return false;
        }

        $ignored = false;
        foreach ($rules as $rule) {
            if (!$this->matchesGitignorePattern($relativePath, $rule['pattern'])) {
                continue;
            }

            $ignored = !$rule['negated'];
        }

        return $ignored;
    }

    private function matchesGitignorePattern(string $relativePath, string $pattern): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $anchored = str_starts_with($pattern, '/');
        $pattern = ltrim(str_replace('\\', '/', $pattern), '/');

        if ($pattern === '') {
            return false;
        }

        if (str_ends_with($pattern, '/')) {
            $dir = rtrim($pattern, '/');
            if ($dir === '') {
                return false;
            }

            if ($anchored) {
                return $relativePath === $dir || str_starts_with($relativePath, $dir . '/');
            }

            return $relativePath === $dir
                || str_starts_with($relativePath, $dir . '/')
                || str_contains($relativePath, '/' . $dir . '/');
        }

        if (!str_contains($pattern, '/')) {
            if (fnmatch($pattern, basename($relativePath))) {
                return true;
            }
        }

        if ($anchored) {
            return fnmatch($pattern, $relativePath);
        }

        return fnmatch($pattern, $relativePath) || fnmatch('*/' . $pattern, $relativePath);
    }
}
