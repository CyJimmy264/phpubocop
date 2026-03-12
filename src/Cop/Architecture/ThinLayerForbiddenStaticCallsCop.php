<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Architecture;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

final class ThinLayerForbiddenStaticCallsCop implements CopInterface
{
    use ThinLayerPathMatcher;

    public function name(): string
    {
        return 'Architecture/ThinLayerForbiddenStaticCalls';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        if (!$this->shouldCheckThinLayerFile($file->path, $config)) {
            return [];
        }

        $offenses = [];
        AstWalker::walk($file->astNodes(), function (Node $node) use (&$offenses, $file, $config): void {
            if (!$this->isForbiddenStaticCall($node, $config)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid direct framework/business static calls in thin-layer scripts; delegate to lib/services.',
                'warning',
            );
        });

        return $offenses;
    }

    private function isForbiddenStaticCall(Node $node, array $config): bool
    {
        if (!$node instanceof Expr\StaticCall || !$node->class instanceof Name) {
            return false;
        }

        $className = $node->class->toString();
        if ($this->hasForbiddenStaticPrefix($className, $config)) {
            return true;
        }

        $forbiddenStaticClasses = $this->normalizedStrings($config['ForbiddenStaticClasses'] ?? [
            'csaleorder',
            'csalebasket',
            'ciblock',
            'ciblockelement',
            'ccatalogproduct',
        ]);

        return in_array(strtolower($className), $forbiddenStaticClasses, true);
    }

    private function hasForbiddenStaticPrefix(string $className, array $config): bool
    {
        $prefixes = $this->normalizedStrings($config['ForbiddenStaticCallPrefixes'] ?? [
            'bitrix\\sale\\',
            'bitrix\\iblock\\',
            'bitrix\\catalog\\',
        ]);

        $lowerClass = strtolower($className);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($lowerClass, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
