<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class BooleanLiteralComparisonCop implements CopInterface
{
    /** @var list<string> */
    private const FALSEABLE_FUNCTIONS = [
        'array_search',
        'filter_var',
        'iconv',
        'json_encode',
        'mb_convert_encoding',
        'mb_stripos',
        'mb_strpos',
        'mb_strripos',
        'mb_strrpos',
        'preg_match',
        'preg_match_all',
        'strpos',
        'stripos',
        'strrpos',
        'strripos',
    ];

    public function name(): string
    {
        return 'Style/BooleanLiteralComparison';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];
        $falseableVars = [];

        foreach ($file->ast() as $node) {
            $this->walk($node, $file, $offenses, $falseableVars);
        }

        return $offenses;
    }

    /** @param list<Offense> $offenses */
    private function walk(Node $node, SourceFile $file, array &$offenses, array &$falseableVars): void
    {
        if ($this->isScope($node)) {
            $scopeVars = $falseableVars;
            $this->walkChildren($node, $file, $offenses, $scopeVars);
            return;
        }

        if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $name = $node->var->name;
            if ($this->isFalseableExpression($node->expr, $falseableVars)) {
                $falseableVars[$name] = true;
            } else {
                unset($falseableVars[$name]);
            }

            $this->walk($node->expr, $file, $offenses, $falseableVars);
            return;
        }

        if ($node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
            if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
                unset($falseableVars[$node->var->name]);
            }

            if ($node instanceof Expr\AssignOp) {
                $this->walk($node->expr, $file, $offenses, $falseableVars);
            } else {
                $this->walk($node->expr, $file, $offenses, $falseableVars);
            }
            return;
        }

        if ($this->isBooleanComparison($node)) {
            [$literalBool, $otherSide] = $this->extractBooleanComparison($node);
            if (!($literalBool === false && $this->isFalseableExpression($otherSide, $falseableVars))) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Avoid comparing to boolean literals; simplify the condition.'
                );
            }
        }

        $this->walkChildren($node, $file, $offenses, $falseableVars);
    }

    /** @param list<Offense> $offenses */
    private function walkChildren(Node $node, SourceFile $file, array &$offenses, array &$falseableVars): void
    {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $this->walk($subNode, $file, $offenses, $falseableVars);
                continue;
            }

            if (is_array($subNode)) {
                foreach ($subNode as $child) {
                    if ($child instanceof Node) {
                        $this->walk($child, $file, $offenses, $falseableVars);
                    }
                }
            }
        }
    }

    private function isBooleanComparison(Node $node): bool
    {
        if (!$node instanceof Expr\BinaryOp\Identical
            && !$node instanceof Expr\BinaryOp\NotIdentical
            && !$node instanceof Expr\BinaryOp\Equal
            && !$node instanceof Expr\BinaryOp\NotEqual) {
            return false;
        }

        return $this->isBooleanLiteral($node->left) || $this->isBooleanLiteral($node->right);
    }

    /** @return array{0:bool,1:Node} */
    private function extractBooleanComparison(Node $node): array
    {
        if ($this->isBooleanLiteral($node->left)) {
            return [$this->booleanLiteralValue($node->left), $node->right];
        }

        return [$this->booleanLiteralValue($node->right), $node->left];
    }

    private function isBooleanLiteral(Node $node): bool
    {
        return $node instanceof Expr\ConstFetch
            && in_array(strtolower($node->name->toString()), ['true', 'false'], true);
    }

    private function booleanLiteralValue(Node $node): bool
    {
        return strtolower($node->name->toString()) === 'true';
    }

    private function isScope(Node $node): bool
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure
            || $node instanceof Expr\ArrowFunction;
    }

    private function isFalseableExpression(Node $expr, array $falseableVars): bool
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return isset($falseableVars[$expr->name]);
        }

        if (!$expr instanceof Expr\FuncCall || !$expr->name instanceof Node\Name) {
            return false;
        }

        return in_array(strtolower($expr->name->toString()), self::FALSEABLE_FUNCTIONS, true);
    }
}
