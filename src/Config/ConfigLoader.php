<?php

declare(strict_types=1);

namespace PHPuboCop\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    /** @var list<string> */
    private const THIN_LAYER_INHERITED_KEYS = ['Enabled', 'TargetPaths', 'BusinessLayerPaths', 'ExcludePaths'];

    /** @var list<string> */
    private const THIN_LAYER_INHERITORS = [
        'Architecture/ThinLayerComplexity',
        'Architecture/ThinLayerLength',
        'Architecture/ThinLayerSuperglobalUsage',
        'Architecture/ThinLayerGlobalStateUsage',
        'Architecture/ThinLayerIncludeUsage',
        'Architecture/ThinLayerForbiddenFunctions',
        'Architecture/ThinLayerForbiddenMethodCalls',
        'Architecture/ThinLayerForbiddenStaticCalls',
    ];

    public function load(?string $configPath, ?string $profile = null, ?string $targetPath = null): array
    {
        $baseConfig = $this->defaultConfig();
        $loadedConfig = $this->loadedConfig($configPath);
        if ($loadedConfig === null) {
            return $this->applyThinLayerInheritance(
                $this->applyProfile($baseConfig, $profile, $targetPath),
            );
        }

        $effectiveProfile = $this->effectiveProfile($loadedConfig, $profile);
        $merged = array_replace_recursive($baseConfig, $loadedConfig);
        return $this->applyThinLayerInheritance(
            $this->applyProfile($merged, $effectiveProfile, $targetPath),
        );
    }

    private function loadedConfig(?string $configPath): ?array
    {
        if ($configPath === null || !is_file($configPath)) {
            return null;
        }

        $parsed = Yaml::parseFile($configPath);
        return is_array($parsed) ? $parsed : null;
    }

    private function effectiveProfile(array $loadedConfig, ?string $profile): ?string
    {
        if ($profile !== null) {
            return $profile;
        }
        if (!isset($loadedConfig['AllCops']['Profile'])) {
            return null;
        }

        $profileValue = $loadedConfig['AllCops']['Profile'];
        if (!is_string($profileValue)) {
            return null;
        }

        return strtolower($profileValue);
    }

    private function defaultConfig(): array
    {
        return [
            'AllCops' => [
                'EnabledByDefault' => true,
                'UseGitFileList' => true,
                'Exclude' => ['vendor/**'],
                'Include' => ['**/*.php'],
            ],
            'Layout/LineLength' => [
                'Enabled' => true,
                'Max' => 120,
                'IncludeInlineHtml' => false,
            ],
            'Layout/TrailingWhitespace' => [
                'Enabled' => true,
            ],
            'Layout/TrailingCommaInMultiline' => [
                'Enabled' => true,
            ],
            'Layout/IndentationStyle' => [
                'Enabled' => true,
                'Style' => 'spaces',
                'TabWidth' => 4,
            ],
            'Lint/DuplicateArrayKey' => [
                'Enabled' => true,
            ],
            'Lint/DuplicateMethod' => [
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
                'AllowedFilePatterns' => [],
            ],
            'Security/EvalAndDynamicInclude' => [
                'Enabled' => true,
                'AllowedDynamicIncludePatterns' => [
                    '\$_SERVER\s*\[\s*["\']DOCUMENT_ROOT["\']\s*\]',
                ],
            ],
            'Architecture/ThinLayerBoundary' => [
                'Enabled' => false,
                'TargetPaths' => ['**/*.php'],
                'BusinessLayerPaths' => [],
                'ExcludePaths' => [
                    'vendor/**',
                ],
            ],
            'Architecture/ThinLayerComplexity' => [
                'MaxBranchNodes' => 6,
            ],
            'Architecture/ThinLayerLength' => [
                'Max' => 25,
                'CountAsOne' => ['array', 'heredoc', 'call_chain'],
            ],
            'Architecture/ThinLayerSuperglobalUsage' => [
                'ForbiddenSuperglobals' => ['_REQUEST'],
            ],
            'Architecture/ThinLayerGlobalStateUsage' => [
                'CheckGlobalKeyword' => false,
                'ForbiddenGlobals' => ['DB'],
            ],
            'Architecture/ThinLayerIncludeUsage' => [
                'AllowedIncludePatterns' => [
                    '/bitrix/modules/main/include/prolog_before.php',
                    '/bitrix/modules/main/include/prolog_after.php',
                    '/bitrix/modules/main/include/prolog_admin_before.php',
                    '/bitrix/modules/main/include/prolog_admin_after.php',
                    '/bitrix/modules/main/include/epilog_admin.php',
                    '/bitrix/header.php',
                    '/bitrix/footer.php',
                    '/local/php_interface/lib/',
                    '/include/',
                ],
            ],
            'Architecture/ThinLayerForbiddenFunctions' => [
                'ForbiddenFunctions' => ['mysql_query', 'mysqli_query', 'pg_query'],
            ],
            'Architecture/ThinLayerForbiddenMethodCalls' => [
                'ForbiddenMethodPatterns' => [
                    '^(query|exec|fetch|fetchall|fetchassoc|fetchrow)$',
                ],
            ],
            'Architecture/ThinLayerForbiddenStaticCalls' => [
                'ForbiddenStaticCallPrefixes' => [
                    'bitrix\\sale\\',
                    'bitrix\\iblock\\',
                    'bitrix\\catalog\\',
                ],
                'ForbiddenStaticClasses' => [
                    'csaleorder',
                    'csalebasket',
                    'ciblock',
                    'ciblockelement',
                    'ccatalogproduct',
                ],
            ],
            'Style/DoubleQuotes' => [
                'Enabled' => true,
            ],
            'Style/BooleanLiteralComparison' => [
                'Enabled' => true,
            ],
            'Style/EmptyCatch' => [
                'Enabled' => true,
            ],
            'Style/MultilineTernary' => [
                'Enabled' => true,
            ],
            'Style/StrictComparison' => [
                'Enabled' => true,
            ],
        ];
    }

    private function applyProfile(array $config, ?string $profile, ?string $targetPath): array
    {
        if ($profile !== 'bitrix') {
            return $config;
        }

        $rootPrefix = $this->bitrixRootPrefix($targetPath);
        $config['Architecture/ThinLayerBoundary'] = array_replace_recursive(
            $config['Architecture/ThinLayerBoundary'] ?? [],
            [
                'Enabled' => true,
                'TargetPaths' => [$this->prefixPath($rootPrefix, '**')],
                'BusinessLayerPaths' => $this->bitrixBusinessLayerPaths(),
            ],
        );
        return $config;
    }

    /** @return list<string> */
    private function bitrixBusinessLayerPaths(): array
    {
        return [
            'local/php_interface/**',
            'local/modules/*/lib/**',
            'local/modules/*/include.php',
            'local/modules/*/install/index.php',
        ];
    }

    private function applyThinLayerInheritance(array $config): array
    {
        $boundary = [];
        if (is_array($config['Architecture/ThinLayerBoundary'] ?? null)) {
            $boundary = $config['Architecture/ThinLayerBoundary'];
        }

        foreach (self::THIN_LAYER_INHERITORS as $copName) {
            $copConfig = is_array($config[$copName] ?? null) ? $config[$copName] : [];
            $config[$copName] = $this->inheritThinLayerConfig($copConfig, $boundary);
        }

        return $config;
    }

    private function inheritThinLayerConfig(array $copConfig, array $boundaryConfig): array
    {
        foreach (self::THIN_LAYER_INHERITED_KEYS as $key) {
            if (array_key_exists($key, $copConfig)) {
                continue;
            }
            if (!array_key_exists($key, $boundaryConfig)) {
                continue;
            }

            $copConfig[$key] = $boundaryConfig[$key];
        }

        return $copConfig;
    }

    private function bitrixRootPrefix(?string $targetPath): string
    {
        if (!is_string($targetPath) || $targetPath === '') {
            return '';
        }

        $path = is_file($targetPath) ? dirname($targetPath) : $targetPath;
        $real = realpath($path);
        if (!is_string($real)) {
            return '';
        }

        $candidate = $this->findBitrixWebRoot($real);
        if ($candidate === null) {
            return '';
        }

        return basename($candidate);
    }

    private function findBitrixWebRoot(string $start): ?string
    {
        $current = rtrim($start, DIRECTORY_SEPARATOR);
        for ($i = 0; $i < 8; $i++) {
            $found = $this->bitrixWebRootInCurrent($current);
            if ($found !== null) {
                return $found;
            }

            $next = $this->nextParentPath($current);
            if ($next === null) {
                break;
            }
            $current = $next;
        }

        return null;
    }

    private function bitrixWebRootInCurrent(string $current): ?string
    {
        if (is_dir($current . DIRECTORY_SEPARATOR . 'bitrix')) {
            return $current;
        }

        $wwwData = $current . DIRECTORY_SEPARATOR . 'www_data';
        if (is_dir($wwwData . DIRECTORY_SEPARATOR . 'bitrix')) {
            return $wwwData;
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

    private function prefixPath(string $prefix, string $suffix): string
    {
        $prefix = trim($prefix, '/');
        $suffix = ltrim($suffix, '/');
        if ($prefix === '') {
            return $suffix;
        }

        return $prefix . '/' . $suffix;
    }
}
