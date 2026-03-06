<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar;

final class DuplicateArrayKeyCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/DuplicateArrayKey';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        return $this->collectDuplicateKeyOffenses($file);
    }

    private function normalizeKey(Node $key): ?string
    {
        if ($key instanceof Scalar\String_) {
            return 's:' . $key->value;
        }

        if ($key instanceof Scalar\Int_) {
            return 'i:' . $key->value;
        }

        if ($key instanceof Expr\UnaryMinus && $key->expr instanceof Scalar\Int_) {
            return 'i:-' . $key->expr->value;
        }

        return null;
    }

    private function displayKey(string $normalizedKey): string
    {
        if (str_starts_with($normalizedKey, 's:')) {
            return "'" . substr($normalizedKey, 2) . "'";
        }

        return substr($normalizedKey, 2);
    }

    /** @return list<Offense> */
    private function collectDuplicateKeyOffenses(SourceFile $file): array
    {
        $offenses = [];
        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if ($node instanceof Expr\Array_) {
                $this->collectArrayDuplicateKeyOffenses($node, $file, $offenses);
            }
        });

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function collectArrayDuplicateKeyOffenses(Expr\Array_ $array, SourceFile $file, array &$offenses): void
    {
        $seen = [];
        foreach ($array->items as $item) {
            $normalizedKey = $this->normalizedItemKey($item);
            if ($normalizedKey === null) {
                continue;
            }

            if (!isset($seen[$normalizedKey])) {
                $seen[$normalizedKey] = true;
                continue;
            }

            $offenses[] = $this->duplicateKeyOffense($file, (int) $item->getStartLine(), $normalizedKey);
        }
    }

    private function normalizedItemKey(?Expr\ArrayItem $item): ?string
    {
        if ($item === null || $item->key === null) {
            return null;
        }

        return $this->normalizeKey($item->key);
    }

    private function duplicateKeyOffense(SourceFile $file, int $line, string $normalizedKey): Offense
    {
        return new Offense(
            $this->name(),
            $file->path,
            $line,
            1,
            sprintf(
                'Duplicate array key %s. Later value overrides previous one.',
                $this->displayKey($normalizedKey),
            ),
        );
    }
}
