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
     *   stats:array{source:string,php_files_seen:int,included:int,excluded_by_config:int,ignored_by_gitignore:int}
     * }
     */
    public function findWithStats(string $path, array $config): array
    {
        $stats = $this->newStats();

        if (is_file($path)) {
            return $this->singleFileResult($path, $stats);
        }

        $exclude = $this->excludePatterns($config);
        if ($this->shouldUseGitFileList($config)) {
            $gitResult = $this->findWithGit($path, $exclude, $stats);
            if ($gitResult !== null) {
                return $gitResult;
            }
        }

        return $this->findWithFilesystem($path, $exclude, $stats);
    }

    /**
     * @return array{source:string,php_files_seen:int,included:int,excluded_by_config:int,ignored_by_gitignore:int}
     */
    private function newStats(): array
    {
        return [
            'source' => 'filesystem',
            'php_files_seen' => 0,
            'included' => 0,
            'excluded_by_config' => 0,
            'ignored_by_gitignore' => 0,
        ];
    }

    /** @param array<string,int|string> $stats */
    private function singleFileResult(string $path, array $stats): array
    {
        if (str_ends_with($path, '.php')) {
            $stats['php_files_seen'] = 1;
            $stats['included'] = 1;
        }

        return [
            'files' => [$path],
            'stats' => $stats,
        ];
    }

    /** @return array<int,string> */
    private function excludePatterns(array $config): array
    {
        $exclude = $config['AllCops']['Exclude'] ?? [];
        return is_array($exclude) ? $exclude : [];
    }

    private function shouldUseGitFileList(array $config): bool
    {
        return (bool) ($config['AllCops']['UseGitFileList'] ?? true);
    }

    /** @param array<int,string> $exclude @param array<string,int|string> $stats */
    private function findWithFilesystem(string $path, array $exclude, array $stats): array
    {
        $files = [];
        $gitignoreRules = $this->loadGitignoreRules($path);
        $root = rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');
        $iterator = $this->filesystemIterator($path, $exclude, $gitignoreRules, $root);
        $context = [
            'exclude' => $exclude,
            'gitignoreRules' => $gitignoreRules,
            'root' => $root,
        ];

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                continue;
            }

            $this->collectFilesystemFile($fileInfo, $files, $stats, $context);
        }

        sort($files);

        return [
            'files' => $files,
            'stats' => $stats,
        ];
    }

    private function filesystemIterator(
        string $path,
        array $exclude,
        array $gitignoreRules,
        string $root,
    ): RecursiveIteratorIterator {
        $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            $this->pruneDirectoryCallback($exclude, $gitignoreRules, $root),
        );

        return new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::LEAVES_ONLY);
    }

    /** @return callable(SplFileInfo): bool */
    private function pruneDirectoryCallback(array $exclude, array $gitignoreRules, string $root): callable
    {
        return function (SplFileInfo $current) use ($exclude, $gitignoreRules, $root): bool {
            if (!$current->isDir()) {
                return true;
            }
            $fullPath = str_replace('\\', '/', $current->getPathname());
            $relativeDir = $this->relativePathFromRoot($fullPath, $root);
            if ($relativeDir === '') {
                return true;
            }
            return !$this->shouldPruneDirectory($relativeDir, $exclude, $gitignoreRules);
        };
    }

    /**
     * @param array<int,string> $exclude
     * @param list<array{pattern:string,negated:bool}> $gitignoreRules
     */
    private function shouldPruneDirectory(string $relativeDir, array $exclude, array $gitignoreRules): bool
    {
        return $this->shouldPruneByExclude($relativeDir, $exclude)
            || $this->shouldPruneByGitignore($relativeDir, $gitignoreRules);
    }

    /** @param list<string> $files @param array<string,int|string> $stats @param array<string,mixed> $context */
    private function collectFilesystemFile(SplFileInfo $fileInfo, array &$files, array &$stats, array $context): void
    {
        $filePath = $this->phpFilePath($fileInfo);
        if ($filePath === null) {
            return;
        }
        $stats['php_files_seen']++;
        if ($this->isExcluded($filePath, $context['exclude'])) {
            $stats['excluded_by_config']++;
            return;
        }
        if ($this->isIgnoredByGitignore($filePath, $context['root'], $context['gitignoreRules'])) {
            $stats['ignored_by_gitignore']++;
            return;
        }

        $files[] = $filePath;
        $stats['included']++;
    }

    private function phpFilePath(SplFileInfo $fileInfo): ?string
    {
        if (!$fileInfo->isFile()) {
            return null;
        }
        $filePath = $fileInfo->getPathname();
        return str_ends_with($filePath, '.php') ? $filePath : null;
    }

    /** @param array<int,string> $exclude @param array<string,int|string> $stats */
    private function findWithGit(string $path, array $exclude, array $stats): ?array
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return null;
        }
        $output = $this->gitListPhpCandidates($realPath);
        if ($output === null) {
            return null;
        }
        $files = [];
        foreach (explode("\0", $output) as $entry) {
            $this->collectGitEntry($entry, $realPath, $exclude, $files, $stats);
        }
        $stats['source'] = 'git';
        sort($files);
        return ['files' => $files, 'stats' => $stats];
    }

    private function gitListPhpCandidates(string $realPath): ?string
    {
        $cmd = sprintf(
            'git -C %s ls-files --cached --others --exclude-standard -z -- . 2>/dev/null',
            escapeshellarg($realPath),
        );

        $output = shell_exec($cmd);
        if (!is_string($output) || $output === '') {
            return null;
        }

        return $output;
    }

    /** @param array<int,string> $exclude @param list<string> $files @param array<string,int|string> $stats */
    private function collectGitEntry(
        string $entry,
        string $realPath,
        array $exclude,
        array &$files,
        array &$stats,
    ): void
    {
        if ($entry === '' || !str_ends_with($entry, '.php')) return;
        $stats['php_files_seen']++;
        $fullPath = $realPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
        if ($this->isExcluded($fullPath, $exclude)) {
            $stats['excluded_by_config']++;
            return;
        }
        if (!is_file($fullPath)) return;
        $files[] = $fullPath;
        $stats['included']++;
    }

    /** @param array<int,string> $excludePatterns */
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

    /** @param array<int,string> $excludePatterns */
    private function shouldPruneByExclude(string $relativeDir, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            $base = $this->prunableExcludeBase((string) $pattern);
            if ($base === null) {
                continue;
            }

            if ($relativeDir === $base || str_starts_with($relativeDir, $base . '/')) {
                return true;
            }
        }

        return false;
    }

    private function prunableExcludeBase(string $pattern): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', $pattern), '/');
        if ($normalized === '') {
            return null;
        }
        $base = $this->directoryBasePattern($normalized);
        if ($base === null || $base === '') {
            return null;
        }
        if ($this->hasGlobSymbols($base)) {
            return null;
        }
        return $base;
    }

    private function directoryBasePattern(string $normalized): ?string
    {
        if (str_ends_with($normalized, '/**')) {
            return rtrim(substr($normalized, 0, -3), '/');
        }
        if (str_ends_with($normalized, '/')) {
            return rtrim($normalized, '/');
        }
        return null;
    }

    /**
     * @param list<array{pattern:string,negated:bool}> $rules
     */
    private function shouldPruneByGitignore(string $relativeDir, array $rules): bool
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if ($relativeDir === '') {
            return false;
        }
        foreach ($rules as $rule) {
            if ($this->shouldPruneForGitignoreRule($relativeDir, $rule, $rules)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{pattern:string,negated:bool} $rule
     * @param list<array{pattern:string,negated:bool}> $rules
     */
    private function shouldPruneForGitignoreRule(string $relativeDir, array $rule, array $rules): bool
    {
        $ignoredDir = $this->ignoredDirectoryFromRule($rule);
        if ($ignoredDir === null || !$this->isInsideDirectory($relativeDir, $ignoredDir)) {
            return false;
        }
        if ($this->hasNegatedRuleInsideDirectory($ignoredDir, $rules)) {
            return false;
        }
        return true;
    }

    /** @param array{pattern:string,negated:bool} $rule */
    private function ignoredDirectoryFromRule(array $rule): ?string
    {
        if ($rule['negated']) {
            return null;
        }

        $pattern = ltrim(str_replace('\\', '/', $rule['pattern']), '/');
        if (!str_ends_with($pattern, '/')) {
            return null;
        }

        if ($this->hasGlobSymbols($pattern)) {
            return null;
        }

        $ignoredDir = rtrim($pattern, '/');
        return $ignoredDir === '' ? null : $ignoredDir;
    }

    private function isInsideDirectory(string $path, string $dir): bool
    {
        return $path === $dir || str_starts_with($path, $dir . '/');
    }

    /**
     * @param list<array{pattern:string,negated:bool}> $rules
     */
    private function hasNegatedRuleInsideDirectory(string $ignoredDir, array $rules): bool
    {
        $ignoredDir = rtrim($ignoredDir, '/');

        foreach ($rules as $rule) {
            if (!$rule['negated']) {
                continue;
            }
            $pattern = $this->normalizedNegatedPattern($rule['pattern']);
            if ($pattern === '') {
                continue;
            }
            if ($this->isNegatedPatternInsideDirectory($pattern, $ignoredDir)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedNegatedPattern(string $pattern): string
    {
        return trim(ltrim(str_replace('\\', '/', $pattern), '/'), '/');
    }

    private function isNegatedPatternInsideDirectory(string $pattern, string $ignoredDir): bool
    {
        if ($this->hasGlobSymbols($pattern)) {
            return str_starts_with($pattern, $ignoredDir . '/');
        }
        return $pattern === $ignoredDir || str_starts_with($pattern, $ignoredDir . '/');
    }

    private function hasGlobSymbols(string $pattern): bool
    {
        return str_contains($pattern, '*')
            || str_contains($pattern, '?')
            || str_contains($pattern, '[');
    }

    /**
     * @return list<array{pattern:string,negated:bool}>
     */
    private function loadGitignoreRules(string $rootPath): array
    {
        $gitignorePath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_file($gitignorePath)) {
            return [];
        }

        $rules = [];
        foreach (file($gitignorePath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $parsed = $this->parseGitignoreRule((string) $line);
            if ($parsed !== null) {
                $rules[] = $parsed;
            }
        }

        return $rules;
    }

    /** @return array{pattern:string,negated:bool}|null */
    private function parseGitignoreRule(string $line): ?array
    {
        $rule = trim($line);
        if ($rule === '' || str_starts_with($rule, '#')) {
            return null;
        }

        $negated = str_starts_with($rule, '!');
        if ($negated) {
            $rule = substr($rule, 1);
        }

        if ($rule === '') {
            return null;
        }

        return [
            'pattern' => $rule,
            'negated' => $negated,
        ];
    }

    /**
     * @param list<array{pattern:string,negated:bool}> $rules
     */
    private function isIgnoredByGitignore(string $path, string $root, array $rules): bool
    {
        if ($rules === []) {
            return false;
        }
        $relativePath = $this->relativePathFromRoot(str_replace('\\', '/', $path), $root);
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
        $normalizedPattern = ltrim(str_replace('\\', '/', $pattern), '/');

        if ($normalizedPattern === '') {
            return false;
        }

        if (str_ends_with($normalizedPattern, '/')) {
            return $this->matchesDirectoryGitignorePattern($relativePath, $normalizedPattern, $anchored);
        }

        return $this->matchesFileGitignorePattern($relativePath, $normalizedPattern, $anchored);
    }

    private function matchesDirectoryGitignorePattern(string $relativePath, string $pattern, bool $anchored): bool
    {
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

    private function matchesFileGitignorePattern(string $relativePath, string $pattern, bool $anchored): bool
    {
        if (!str_contains($pattern, '/') && fnmatch($pattern, basename($relativePath))) {
            return true;
        }

        if ($anchored) {
            return fnmatch($pattern, $relativePath);
        }

        return fnmatch($pattern, $relativePath) || fnmatch('*/' . $pattern, $relativePath);
    }
}
