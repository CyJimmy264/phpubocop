<?php

declare(strict_types=1);

namespace PHPuboCop;

use PHPuboCop\Config\ConfigLoader;
use PHPuboCop\Core\Autocorrector;
use PHPuboCop\Core\CopRegistry;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\Runner;
use PHPuboCop\Formatter\FormatterInterface;
use PHPuboCop\Formatter\JsonFormatter;
use PHPuboCop\Formatter\TextFormatter;

final class Application
{
    private bool $bitrixHintPrinted = false;

    public function run(array $argv): int
    {
        [$paths, $configPath, $profile, $format, $autocorrect, $autocorrectAll, $verbose] = $this->parseArgs($argv);

        $cops = CopRegistry::default();
        $configLoader = new ConfigLoader();
        $runner = new Runner($cops);
        $offenses = [];
        $inspectedFiles = [];
        $runContext = [
            'configPath' => $configPath,
            'profile' => $profile,
            'autocorrect' => $autocorrect,
            'autocorrectAll' => $autocorrectAll,
            'verbose' => $verbose,
            'cops' => $cops,
            'configLoader' => $configLoader,
            'runner' => $runner,
        ];

        foreach ($paths as $path) {
            $this->runForPath($path, $runContext, $offenses, $inspectedFiles);
        }

        return $this->finalizeRun($offenses, $inspectedFiles, $format);
    }

    /**
     * @param array{
     *   configPath:?string,
     *   profile:?string,
     *   autocorrect:bool,
     *   autocorrectAll:bool,
     *   verbose:bool,
     *   cops:list<object>,
     *   configLoader:ConfigLoader,
     *   runner:Runner
     * } $context
     * @param list<Offense> $offenses
     * @param array<string,bool> $inspectedFiles
     */
    private function runForPath(
        string $path,
        array $context,
        array &$offenses,
        array &$inspectedFiles,
    ): void {
        $resolvedConfig = $this->resolveConfigPathForTarget($path, $context['configPath']);
        $config = $context['configLoader']->load($resolvedConfig['path'], $context['profile'], $path);

        $this->runAutocorrectIfEnabled($path, $config, $context);
        $this->printBitrixProfileHintIfNeeded($path, $config, $context['profile']);
        $this->printVerboseConfigIfEnabled($path, $resolvedConfig, $config, $context['verbose']);
        $this->collectOffensesFromRunner($context['runner'], $path, $config, $offenses);

        $this->collectInspectedFiles($context['runner']->lastInspectedFiles(), $inspectedFiles);

        if ($context['verbose']) {
            $this->printVerboseDiscovery($context['runner']->lastFileStats());
        }
    }

    /** @param list<string> $files @param array<string,bool> $inspectedFiles */
    private function collectInspectedFiles(array $files, array &$inspectedFiles): void
    {
        foreach ($files as $filePath) {
            $inspectedFiles[$filePath] = true;
        }
    }

    /** @param list<Offense> $offenses @param array<string,bool> $inspectedFiles */
    private function finalizeRun(array $offenses, array $inspectedFiles, string $format): int
    {
        usort(
            $offenses,
            static fn (Offense $a, Offense $b): int =>
                [$a->file, $a->line, $a->column, $a->copName]
                <=>
                [$b->file, $b->line, $b->column, $b->copName],
        );

        $formatter = $this->resolveFormatter($format);
        echo $formatter->format(
            $offenses,
            ['inspected_files' => array_keys($inspectedFiles)],
        );

        return $offenses === [] ? 0 : 1;
    }

    private function parseArgs(array $argv): array
    {
        $state = [
            'paths' => [],
            'configPath' => null,
            'profile' => null,
            'format' => 'text',
            'autocorrect' => false,
            'autocorrectAll' => false,
            'verbose' => false,
        ];

        for ($i = 1, $count = count($argv); $i < $count; $i++) {
            $arg = (string) $argv[$i];
            if ($this->isHelpArg($arg)) {
                $this->printHelp();
                exit(0);
            }

            $this->consumeArg($arg, $argv, $i, $state);
        }

        if ($state['paths'] === []) {
            $state['paths'][] = '.';
        }

        return [
            $state['paths'],
            $state['configPath'],
            $state['profile'],
            strtolower((string) $state['format']),
            (bool) $state['autocorrect'],
            (bool) $state['autocorrectAll'],
            (bool) $state['verbose'],
        ];
    }

    private function isHelpArg(string $arg): bool
    {
        return $arg === '--help' || $arg === '-h';
    }

    /**
     * @param array{
     *   paths:list<string>,
     *   configPath:?string,
     *   profile:?string,
     *   format:string,
     *   autocorrect:bool,
     *   autocorrectAll:bool,
     *   verbose:bool
     * } $state
     */
    private function consumeArg(string $arg, array $argv, int &$i, array &$state): void
    {
        if ($this->consumeConfigArg($arg, $argv, $i, $state)) {
            return;
        }
        if ($this->consumeProfileArg($arg, $argv, $i, $state)) {
            return;
        }
        if ($this->consumeFormatArg($arg, $argv, $i, $state)) {
            return;
        }
        if ($this->consumeToggleArg($arg, $state)) {
            return;
        }

        if (!str_starts_with($arg, '--')) {
            $state['paths'][] = $arg;
        }
    }

    /** @param array{profile:?string} $state */
    private function consumeProfileArg(string $arg, array $argv, int &$i, array &$state): bool
    {
        if (str_starts_with($arg, '--profile=')) {
            $state['profile'] = strtolower((string) substr($arg, strlen('--profile=')));
            return true;
        }
        if ($arg !== '--profile') {
            return false;
        }

        $i++;
        $state['profile'] = isset($argv[$i]) ? strtolower((string) $argv[$i]) : null;
        return true;
    }

    /** @param array{configPath:?string} $state */
    private function consumeConfigArg(string $arg, array $argv, int &$i, array &$state): bool
    {
        if (str_starts_with($arg, '--config=')) {
            $state['configPath'] = substr($arg, strlen('--config='));
            return true;
        }
        if ($arg !== '--config') {
            return false;
        }

        $i++;
        $state['configPath'] = $argv[$i] ?? null;
        return true;
    }

    /** @param array{format:string} $state */
    private function consumeFormatArg(string $arg, array $argv, int &$i, array &$state): bool
    {
        if (str_starts_with($arg, '--format=')) {
            $state['format'] = substr($arg, strlen('--format='));
            return true;
        }
        if ($arg !== '--format') {
            return false;
        }

        $i++;
        $state['format'] = $argv[$i] ?? 'text';
        return true;
    }

    /** @param array{autocorrect:bool,autocorrectAll:bool,verbose:bool} $state */
    private function consumeToggleArg(string $arg, array &$state): bool
    {
        if ($arg === '--autocorrect' || $arg === '-a') {
            $state['autocorrect'] = true;
            return true;
        }
        if ($arg === '--autocorrect-all' || $arg === '-A') {
            $state['autocorrect'] = true;
            $state['autocorrectAll'] = true;
            return true;
        }
        if ($arg === '--verbose' || $arg === '-v') {
            $state['verbose'] = true;
            return true;
        }

        return false;
    }

    /** @return array{path:?string,source:string} */
    private function resolveConfigPathForTarget(string $targetPath, ?string $explicitConfigPath): array
    {
        if ($explicitConfigPath !== null) {
            return ['path' => $explicitConfigPath, 'source' => 'explicit'];
        }

        $targetConfig = $this->findTargetConfigPath($targetPath);
        if ($targetConfig !== null) {
            return $targetConfig;
        }

        if (is_file('.phpubocop.yml')) {
            return ['path' => '.phpubocop.yml', 'source' => 'current_working_directory'];
        }

        return ['path' => null, 'source' => 'defaults'];
    }

    /** @param array<string,mixed> $config */
    private function runAutocorrectIfEnabled(string $path, array $config, array $context): void
    {
        if (!$context['autocorrect']) {
            return;
        }

        (new Autocorrector($context['cops']))->run([$path], $config, $context['autocorrectAll']);
    }

    /** @param array<string,mixed> $resolvedConfig @param array<string,mixed> $config */
    private function printVerboseConfigIfEnabled(
        string $path,
        array $resolvedConfig,
        array $config,
        bool $verbose,
    ): void {
        if (!$verbose) {
            return;
        }

        $this->printVerboseConfig($path, $resolvedConfig, $config);
    }

    /** @param array<string,mixed> $config @param list<Offense> $offenses */
    private function collectOffensesFromRunner(
        Runner $runner,
        string $path,
        array $config,
        array &$offenses,
    ): void {
        foreach ($runner->run($path, $config) as $offense) {
            $offenses[] = $offense;
        }
    }

    /** @return array{path:string,source:string}|null */
    private function findTargetConfigPath(string $targetPath): ?array
    {
        $startDir = $this->targetConfigSearchStartDir($targetPath);
        if ($startDir === null) {
            return null;
        }

        $configDir = $this->nearestConfigDir($startDir);
        if ($configDir === null) {
            return null;
        }

        return [
            'path' => $configDir . DIRECTORY_SEPARATOR . '.phpubocop.yml',
            'source' => $configDir === $startDir ? 'target_directory' : 'target_ancestor_directory',
        ];
    }

    private function nearestConfigDir(string $startDir): ?string
    {
        $currentDir = $startDir;
        while (true) {
            $candidate = $currentDir . DIRECTORY_SEPARATOR . '.phpubocop.yml';
            if (is_file($candidate)) {
                return $currentDir;
            }

            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                return null;
            }

            $currentDir = $parentDir;
        }
    }

    private function targetConfigSearchStartDir(string $targetPath): ?string
    {
        if (is_dir($targetPath)) {
            return rtrim($targetPath, DIRECTORY_SEPARATOR);
        }

        if (is_file($targetPath)) {
            return dirname($targetPath);
        }

        return null;
    }

    private function resolveFormatter(string $format): FormatterInterface
    {
        return match ($format) {
            'json' => new JsonFormatter(),
            default => new TextFormatter(),
        };
    }

    private function printHelp(): void
    {
        echo <<<TXT
PHPuboCop - RuboCop-inspired linter for PHP

Usage:
  phpubocop [path ...] [--config=.phpubocop.yml] [--profile=bitrix]
            [--format=text|json] [--autocorrect|-a] [--autocorrect-all|-A] [--verbose|-v]

Examples:
  phpubocop src
  phpubocop src tests
  phpubocop . --profile=bitrix
  phpubocop . --format=json
  phpubocop src --autocorrect
  phpubocop src -a
  phpubocop src --autocorrect-all
  phpubocop src -A
  phpubocop src --verbose

TXT;
    }

    private function printVerboseConfig(string $path, array $resolvedConfig, array $config): void
    {
        $configPath = $resolvedConfig['path'] ?? '<defaults>';
        $exclude = $config['AllCops']['Exclude'] ?? [];
        $excludeText = $exclude === []
            ? '(none)'
            : implode(', ', array_map(static fn ($item): string => (string) $item, $exclude));

        fwrite(STDERR, sprintf("[phpubocop] target: %s\n", $path));
        fwrite(STDERR, sprintf("[phpubocop] config: %s (%s)\n", $configPath, $resolvedConfig['source']));
        fwrite(STDERR, sprintf("[phpubocop] excludes: %s\n", $excludeText));
    }

    private function printBitrixProfileHintIfNeeded(string $path, array $config, ?string $profile): void
    {
        $bitrixPrefix = $this->bitrixHintPrefix($path, $config, $profile);
        if ($bitrixPrefix === null) {
            return;
        }

        fwrite(
            STDERR,
            $this->bitrixHintMessage($bitrixPrefix),
        );
        $this->bitrixHintPrinted = true;
    }

    private function bitrixHintPrefix(string $path, array $config, ?string $profile): ?string
    {
        if ($this->bitrixHintPrinted || $profile === 'bitrix') {
            return null;
        }

        $enabled = (bool) (($config['Architecture/ThinLayerBoundary']['Enabled'] ?? false));
        if ($enabled) {
            return null;
        }

        return $this->detectBitrixPrefix($path);
    }

    private function bitrixHintMessage(string $bitrixPrefix): string
    {
        [$target, $businessPaths] = $this->bitrixGuardPaths($bitrixPrefix);
        $businessLines = implode(
            "\n",
            array_map(static fn (string $path): string => "    - {$path}", $businessPaths),
        );
        return sprintf(
            "[phpubocop] Detected Bitrix project. Suggested .phpubocop.yml block:\n"
            . "Architecture/ThinLayerBoundary:\n"
            . "  Enabled: true\n"
            . "  TargetPaths:\n    - %s\n"
            . "  BusinessLayerPaths:\n%s\n",
            $target,
            $businessLines,
        );
    }

    /** @return array{0:string,1:list<string>} */
    private function bitrixGuardPaths(string $bitrixPrefix): array
    {
        return [
            $this->suggestedPath($bitrixPrefix, '**'),
            [
                'local/php_interface/**',
                'local/modules/*/lib/**',
                'local/modules/*/include.php',
                'local/modules/*/install/index.php',
            ],
        ];
    }

    private function detectBitrixPrefix(string $path): ?string
    {
        $current = $this->resolvedTargetDirectory($path);
        if ($current === null) {
            return null;
        }

        return $this->bitrixPrefixInAncestorChain($current);
    }

    private function bitrixPrefixInAncestorChain(string $current): ?string
    {
        for ($i = 0; $i < 8; $i++) {
            $prefix = $this->bitrixPrefixInCurrent($current);
            if ($prefix !== null) {
                return $prefix;
            }

            $next = $this->nextParentPath($current);
            if ($next === null) {
                break;
            }
            $current = $next;
        }

        return null;
    }

    private function resolvedTargetDirectory(string $path): ?string
    {
        $candidate = is_file($path) ? dirname($path) : $path;
        $realPath = realpath($candidate);
        if (!is_string($realPath)) {
            return null;
        }

        return rtrim($realPath, DIRECTORY_SEPARATOR);
    }

    private function bitrixPrefixInCurrent(string $current): ?string
    {
        if (is_dir($current . DIRECTORY_SEPARATOR . 'bitrix')) {
            return '';
        }
        if (is_dir($current . DIRECTORY_SEPARATOR . 'www_data' . DIRECTORY_SEPARATOR . 'bitrix')) {
            return 'www_data';
        }

        return null;
    }

    private function nextParentPath(string $current): ?string
    {
        $parent = dirname($current);
        if ($parent === $current) {
            return null;
        }

        return $parent;
    }

    private function suggestedPath(string $prefix, string $suffix): string
    {
        $prefix = trim($prefix, '/');
        $suffix = ltrim($suffix, '/');
        if ($prefix === '') {
            return $suffix;
        }

        return $prefix . '/' . $suffix;
    }

    private function printVerboseDiscovery(array $stats): void
    {
        $source = (string) ($stats['source'] ?? 'filesystem');
        fwrite(
            STDERR,
            sprintf(
                "[phpubocop] files(%s): php_seen=%d, included=%d, excluded_by_config=%d, ignored_by_gitignore=%d\n",
                $source,
                (int) ($stats['php_files_seen'] ?? 0),
                (int) ($stats['included'] ?? 0),
                (int) ($stats['excluded_by_config'] ?? 0),
                (int) ($stats['ignored_by_gitignore'] ?? 0),
            ),
        );
    }
}
