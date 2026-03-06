<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Security;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

final class UnserializeCop implements CopInterface
{
    public function name(): string
    {
        return 'Security/Unserialize';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Expr\FuncCall || !$this->isUnserializeCall($node)) {
                return;
            }

            if ($this->hasStrictAllowedClasses($node)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Avoid unserialize() without strict allowed_classes control.',
                'warning',
            );
        });

        return $offenses;
    }

    private function isUnserializeCall(Expr\FuncCall $call): bool
    {
        return $call->name instanceof Name && strtolower($call->name->toString()) === 'unserialize';
    }

    private function hasStrictAllowedClasses(Expr\FuncCall $call): bool
    {
        $options = $this->optionsArrayArg($call);
        if ($options === null) {
            return false;
        }

        foreach ($options->items as $item) {
            if (!$this->isAllowedClassesKey($item)) {
                continue;
            }

            return $this->isFalseConst($item->value);
        }

        return false;
    }

    private function optionsArrayArg(Expr\FuncCall $call): ?Expr\Array_
    {
        if (count($call->args) < 2) {
            return null;
        }

        $options = $call->args[1]->value;
        return $options instanceof Expr\Array_ ? $options : null;
    }

    private function isAllowedClassesKey(?Expr\ArrayItem $item): bool
    {
        if ($item === null || !$item->key instanceof String_) {
            return false;
        }

        return strtolower($item->key->value) === 'allowed_classes';
    }

    private function isFalseConst(Node $value): bool
    {
        return $value instanceof Expr\ConstFetch
            && strtolower($value->name->toString()) === 'false';
    }
}
