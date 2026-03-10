<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testThinLayerBoundaryIsDisabledByDefault(): void
    {
        $loader = new ConfigLoader();

        $config = $loader->load(null);

        self::assertFalse((bool) ($config['Architecture/ThinLayerBoundary']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerBoundary']['TargetPaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerForbiddenStaticCalls']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerForbiddenFunctions']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerForbiddenFunctions']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerComplexity']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerComplexity']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerComplexity']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerLength']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerLength']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerLength']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['array', 'heredoc', 'call_chain'],
            $config['Architecture/ThinLayerLength']['CountAsOne'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerSuperglobalUsage']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerSuperglobalUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerForbiddenMethodCalls']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerGlobalStateUsage']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerGlobalStateUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['ExcludePaths'] ?? [],
        );
        self::assertFalse((bool) ($config['Architecture/ThinLayerIncludeUsage']['Enabled'] ?? true));
        self::assertSame(
            ['**/*.php'],
            $config['Architecture/ThinLayerIncludeUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerIncludeUsage']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['\$_SERVER\s*\[\s*["\']DOCUMENT_ROOT["\']\s*\]'],
            $config['Security/EvalAndDynamicInclude']['AllowedDynamicIncludePatterns'] ?? [],
        );
        self::assertFalse((bool) ($config['Layout/LineLength']['IncludeInlineHtml'] ?? true));
    }

    public function testThinLayerChildCopCanOverrideInheritedEnabled(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_loader_enabled_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $configFile = $dir . '/.phpubocop.yml';
        file_put_contents($configFile, <<<'YAML'
Architecture/ThinLayerBoundary:
  Enabled: false

Architecture/ThinLayerForbiddenFunctions:
  Enabled: true
YAML);

        $loader = new ConfigLoader();
        $config = $loader->load($configFile);

        self::assertFalse((bool) ($config['Architecture/ThinLayerBoundary']['Enabled'] ?? true));
        self::assertTrue((bool) ($config['Architecture/ThinLayerForbiddenFunctions']['Enabled'] ?? false));
        self::assertFalse((bool) ($config['Architecture/ThinLayerForbiddenStaticCalls']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerComplexity']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerLength']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerSuperglobalUsage']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerForbiddenMethodCalls']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerGlobalStateUsage']['Enabled'] ?? true));
        self::assertFalse((bool) ($config['Architecture/ThinLayerIncludeUsage']['Enabled'] ?? true));
    }

    public function testBitrixProfileEnablesThinLayerBoundary(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_loader_' . uniqid('', true);
        mkdir($dir . '/www_data/bitrix', 0777, true);

        $loader = new ConfigLoader();
        $config = $loader->load(null, 'bitrix', $dir);
        $expectedBusinessPaths = [
            'local/php_interface/**',
            'local/modules/*/lib/**',
            'local/modules/*/include.php',
            'local/modules/*/install/index.php',
        ];

        self::assertTrue((bool) ($config['Architecture/ThinLayerBoundary']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerBoundary']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerBoundary']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerForbiddenStaticCalls']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerForbiddenStaticCalls']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerForbiddenFunctions']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerForbiddenFunctions']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerComplexity']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerComplexity']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerComplexity']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerLength']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerLength']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerLength']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['array', 'heredoc', 'call_chain'],
            $config['Architecture/ThinLayerLength']['CountAsOne'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerSuperglobalUsage']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerSuperglobalUsage']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerForbiddenMethodCalls']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerForbiddenMethodCalls']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerGlobalStateUsage']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerGlobalStateUsage']['BusinessLayerPaths'] ?? [],
        );
        self::assertTrue((bool) ($config['Architecture/ThinLayerIncludeUsage']['Enabled'] ?? false));
        self::assertSame(
            ['www_data/**'],
            $config['Architecture/ThinLayerIncludeUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            $expectedBusinessPaths,
            $config['Architecture/ThinLayerIncludeUsage']['BusinessLayerPaths'] ?? [],
        );
    }

    public function testBitrixProfileWithoutDetectedRootUsesNeutralPaths(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_loader_plain_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $loader = new ConfigLoader();
        $config = $loader->load(null, 'bitrix', $dir);

        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerBoundary']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerComplexity']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerLength']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['**'],
            $config['Architecture/ThinLayerIncludeUsage']['TargetPaths'] ?? [],
        );
    }

    public function testThinLayerForbiddenStaticCallsCanOverrideInheritedPaths(): void
    {
        $dir = sys_get_temp_dir() . '/phpubocop_cfg_loader_override_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $configFile = $dir . '/.phpubocop.yml';
        file_put_contents($configFile, <<<'YAML'
Architecture/ThinLayerBoundary:
  TargetPaths:
    - src/**
  BusinessLayerPaths:
    - src/Domain/**
  ExcludePaths:
    - vendor/**

Architecture/ThinLayerForbiddenStaticCalls:
  TargetPaths:
    - app/**
Architecture/ThinLayerForbiddenFunctions:
  TargetPaths:
    - api/**
Architecture/ThinLayerComplexity:
  TargetPaths:
    - pages/**
Architecture/ThinLayerLength:
  TargetPaths:
    - handlers/**
Architecture/ThinLayerSuperglobalUsage:
  TargetPaths:
    - controllers/**
Architecture/ThinLayerForbiddenMethodCalls:
  TargetPaths:
    - handlers/**
Architecture/ThinLayerGlobalStateUsage:
  TargetPaths:
    - pages/**
Architecture/ThinLayerIncludeUsage:
  TargetPaths:
    - pages/**
YAML);

        $loader = new ConfigLoader();
        $config = $loader->load($configFile);

        self::assertSame(
            ['app/**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenStaticCalls']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['api/**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenFunctions']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['pages/**'],
            $config['Architecture/ThinLayerComplexity']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerComplexity']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerComplexity']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['handlers/**'],
            $config['Architecture/ThinLayerLength']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerLength']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerLength']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['controllers/**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerSuperglobalUsage']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['handlers/**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerForbiddenMethodCalls']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['pages/**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerGlobalStateUsage']['ExcludePaths'] ?? [],
        );
        self::assertSame(
            ['pages/**'],
            $config['Architecture/ThinLayerIncludeUsage']['TargetPaths'] ?? [],
        );
        self::assertSame(
            ['src/Domain/**'],
            $config['Architecture/ThinLayerIncludeUsage']['BusinessLayerPaths'] ?? [],
        );
        self::assertSame(
            ['vendor/**'],
            $config['Architecture/ThinLayerIncludeUsage']['ExcludePaths'] ?? [],
        );
    }
}
