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
        return $this->findWithStats($path, $config)['files'];
    }

    /**
     * @return array{
     *   files:list<string>,
     *   stats:array{
     *     php_files_seen:int,
     *     included:int,
     *     excluded_by_config:int,
     *     ignored_by_gitignore:int
     *   }
     * }
     */
    public function findWithStats(string $path, array $config): array
    {
        $stats = [
            'php_files_seen' => 0,
            'included' => 0,
            'excluded_by_config' => 0,
            'ignored_by_gitignore' => 0,
        ];

        if (is_file($path)) {
            if (str_ends_with($path, '.php')) {
                $stats['php_files_seen'] = 1;
                $stats['included'] = 1;
            }

            return [
                'files' => [$path],
                'stats' => $stats,
            ];
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
            $stats['php_files_seen']++;

            if ($this->isExcluded($filePath, $exclude)) {
                $stats['excluded_by_config']++;
                continue;
            }

            if ($this->isIgnoredByGitignore($filePath, $root, $gitignoreRules)) {
                $stats['ignored_by_gitignore']++;
                continue;
            }

            $files[] = $filePath;
            $stats['included']++;
        }

        sort($files);
        return [
            'files' => $files,
            'stats' => $stats,
        ];
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
