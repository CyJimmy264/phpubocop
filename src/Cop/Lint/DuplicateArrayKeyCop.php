<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Lint;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

final class DuplicateArrayKeyCop implements CopInterface
{
    public function name(): string
    {
        return 'Lint/DuplicateArrayKey';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Expr\Array_) {
                return;
            }

            $seen = [];
            foreach ($node->items as $item) {
                if ($item === null || $item->key === null) {
                    continue;
                }

                $normalizedKey = $this->normalizeKey($item->key);
                if ($normalizedKey === null) {
                    continue;
                }

                if (isset($seen[$normalizedKey])) {
                    $offenses[] = new Offense(
                        $this->name(),
                        $file->path,
                        (int) $item->getStartLine(),
                        1,
                        sprintf('Duplicate array key %s. Later value overrides previous one.', $this->displayKey($normalizedKey)),
                    );
                    continue;
                }

                $seen[$normalizedKey] = true;
            }
        });

        return $offenses;
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
}
