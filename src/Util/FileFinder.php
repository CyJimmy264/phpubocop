<?php

declare(strict_types=1);

namespace PHPuboCop\Util;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
     *     source:string,
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
            'source' => 'filesystem',
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

        $exclude = $config['AllCops']['Exclude'] ?? [];
        $useGitFileList = (bool) ($config['AllCops']['UseGitFileList'] ?? true);
        if ($useGitFileList) {
            $gitResult = $this->findWithGit($path, $exclude, $stats);
            if ($gitResult !== null) {
                return $gitResult;
            }
        }

        $files = [];
        $gitignoreRules = $this->loadGitignoreRules($path);
        $root = rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');

        $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            function (SplFileInfo $current) use ($exclude, $gitignoreRules, $root): bool {
                if (!$current->isDir()) {
                    return true;
                }

                $fullPath = str_replace('\\', '/', $current->getPathname());
                $relativeDir = $this->relativePathFromRoot($fullPath, $root);
                if ($relativeDir === '') {
                    return true;
                }

                if ($this->shouldPruneByExclude($relativeDir, $exclude)) {
                    return false;
                }

                if ($this->shouldPruneByGitignore($relativeDir, $gitignoreRules)) {
                    return false;
                }

                return true;
            },
        );

        $iterator = new RecursiveIteratorIterator(
            $filtered,
            RecursiveIteratorIterator::LEAVES_ONLY,
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

    /**
     * @param array<int,string> $exclude
     * @param array{source:string,php_files_seen:int,included:int,excluded_by_config:int,ignored_by_gitignore:int} $stats
     * @return array{files:list<string>,stats:array{source:string,php_files_seen:int,included:int,excluded_by_config:int,ignored_by_gitignore:int}}|null
     */
    private function findWithGit(string $path, array $exclude, array $stats): ?array
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return null;
        }

        $cmd = sprintf(
            'git -C %s ls-files --cached --others --exclude-standard -z -- . 2>/dev/null',
            escapeshellarg($realPath),
        );
        $output = shell_exec($cmd);
        if (!is_string($output) || $output === '') {
            return null;
        }

        $files = [];
        $entries = explode("\0", $output);
        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            $stats['php_files_seen']++;
            $fullPath = $realPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
            if ($this->isExcluded($fullPath, $exclude)) {
                $stats['excluded_by_config']++;
                continue;
            }

            if (is_file($fullPath)) {
                $files[] = $fullPath;
                $stats['included']++;
            }
        }

        $stats['source'] = 'git';
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

    private function relativePathFromRoot(string $normalizedPath, string $root): string
    {
        if (!str_starts_with($normalizedPath, $root)) {
            return ltrim($normalizedPath, '/');
        }

        return ltrim(substr($normalizedPath, strlen($root)), '/');
    }

    private function shouldPruneByExclude(string $relativeDir, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            $normalized = ltrim(str_replace('\\', '/', (string) $pattern), '/');
            if ($normalized === '') {
                continue;
            }

            // Safe prune only for explicit directory-style excludes.
            $base = null;
            if (str_ends_with($normalized, '/**')) {
                $base = rtrim(substr($normalized, 0, -3), '/');
            } elseif (str_ends_with($normalized, '/')) {
                $base = rtrim($normalized, '/');
            }

            if ($base === null || $base === '') {
                continue;
            }

            if (str_contains($base, '*') || str_contains($base, '?') || str_contains($base, '[')) {
                continue;
            }

            if ($relativeDir === $base || str_starts_with($relativeDir, $base . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{pattern: string, negated: bool}> $rules
     */
    private function shouldPruneByGitignore(string $relativeDir, array $rules): bool
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if ($relativeDir === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($rule['negated']) {
                continue;
            }

            $pattern = ltrim(str_replace('\\', '/', $rule['pattern']), '/');
            if (!str_ends_with($pattern, '/')) {
                continue;
            }

            // Safe prune only for simple directory patterns without glob syntax.
            if (str_contains($pattern, '*') || str_contains($pattern, '?') || str_contains($pattern, '[')) {
                continue;
            }

            $ignoredDir = rtrim($pattern, '/');
            if ($ignoredDir === '') {
                continue;
            }

            if ($relativeDir !== $ignoredDir && !str_starts_with($relativeDir, $ignoredDir . '/')) {
                continue;
            }

            if ($this->hasNegatedRuleInsideDirectory($ignoredDir, $rules)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<array{pattern: string, negated: bool}> $rules
     */
    private function hasNegatedRuleInsideDirectory(string $ignoredDir, array $rules): bool
    {
        $ignoredDir = rtrim($ignoredDir, '/');

        foreach ($rules as $rule) {
            if (!$rule['negated']) {
                continue;
            }

            $pattern = ltrim(str_replace('\\', '/', $rule['pattern']), '/');
            if ($pattern === '') {
                continue;
            }

            // Any glob in negation means we cannot safely prune this subtree.
            if (str_contains($pattern, '*') || str_contains($pattern, '?') || str_contains($pattern, '[')) {
                if (str_starts_with(trim($pattern, '/'), $ignoredDir . '/')) {
                    return true;
                }
                continue;
            }

            $negatedPath = rtrim($pattern, '/');
            if ($negatedPath === $ignoredDir || str_starts_with($negatedPath, $ignoredDir . '/')) {
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
