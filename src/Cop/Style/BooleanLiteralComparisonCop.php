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
    /** @var list<class-string<Node>> */
    private const BOOLEAN_OPERATOR_NODES = [
        Expr\BinaryOp\BooleanAnd::class,
        Expr\BinaryOp\BooleanOr::class,
        Expr\BinaryOp\LogicalAnd::class,
        Expr\BinaryOp\LogicalOr::class,
        Expr\BinaryOp\Equal::class,
        Expr\BinaryOp\NotEqual::class,
        Expr\BinaryOp\Identical::class,
        Expr\BinaryOp\NotIdentical::class,
        Expr\BinaryOp\Smaller::class,
        Expr\BinaryOp\SmallerOrEqual::class,
        Expr\BinaryOp\Greater::class,
        Expr\BinaryOp\GreaterOrEqual::class,
        Expr\BinaryOp\Spaceship::class,
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
            $this->walkScopeNode($node, $file, $offenses, $falseableVars);
            return;
        }
        if ($this->handleAssignNode($node, $file, $offenses, $falseableVars)) {
            return;
        }
        if ($this->handleAssignOpLikeNode($node, $file, $offenses, $falseableVars)) {
            return;
        }
        if ($this->shouldReportBooleanLiteralComparison($node, $falseableVars)) {
            $this->appendComparisonOffense($node, $file, $offenses);
        }

        $this->walkChildren($node, $file, $offenses, $falseableVars);
    }

    /** @param list<Offense> $offenses */
    private function walkScopeNode(Node $node, SourceFile $file, array &$offenses, array $falseableVars): void
    {
        $scopeVars = $falseableVars;
        $this->walkChildren($node, $file, $offenses, $scopeVars);
    }

    /** @param list<Offense> $offenses */
    private function handleAssignNode(Node $node, SourceFile $file, array &$offenses, array &$falseableVars): bool
    {
        if (!$node instanceof Expr\Assign || !$node->var instanceof Expr\Variable || !is_string($node->var->name)) {
            return false;
        }

        $name = $node->var->name;
        if ($this->isFalseableExpression($node->expr, $falseableVars)) {
            $falseableVars[$name] = true;
        } else {
            unset($falseableVars[$name]);
        }

        $this->walk($node->expr, $file, $offenses, $falseableVars);
        return true;
    }

    /** @param list<Offense> $offenses */
    private function handleAssignOpLikeNode(Node $node, SourceFile $file, array &$offenses, array &$falseableVars): bool
    {
        if (!$node instanceof Expr\AssignOp && !$node instanceof Expr\AssignRef) {
            return false;
        }

        if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
            unset($falseableVars[$node->var->name]);
        }

        $this->walk($node->expr, $file, $offenses, $falseableVars);
        return true;
    }

    private function shouldReportBooleanLiteralComparison(Node $node, array $falseableVars): bool
    {
        if (!$this->isBooleanComparison($node)) {
            return false;
        }

        [$literalBool, $otherSide] = $this->extractBooleanComparison($node);
        if ($literalBool === false && $this->isFalseableExpression($otherSide, $falseableVars)) {
            return false;
        }

        return $this->isObviouslyBooleanExpression($otherSide);
    }

    /** @param list<Offense> $offenses */
    private function appendComparisonOffense(Node $node, SourceFile $file, array &$offenses): void
    {
        $offenses[] = new Offense(
            $this->name(),
            $file->path,
            (int) $node->getStartLine(),
            1,
            'Avoid comparing to boolean literals; simplify the condition.',
        );
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

    private function isObviouslyBooleanExpression(Node $expr): bool
    {
        return $this->isBooleanLiteralExpression($expr)
            || $this->isIntrinsicBooleanExpression($expr)
            || $this->isBooleanOperatorExpression($expr)
            || $this->isBooleanLikeNamedExpression($expr);
    }

    private function isBooleanLiteralExpression(Node $expr): bool
    {
        if (!$expr instanceof Expr\ConstFetch) {
            return false;
        }

        $name = strtolower($expr->name->toString());
        return $name === 'true' || $name === 'false';
    }

    private function isIntrinsicBooleanExpression(Node $expr): bool
    {
        return $expr instanceof Expr\BooleanNot
            || $expr instanceof Expr\Empty_
            || $expr instanceof Expr\Isset_
            || $expr instanceof Expr\Instanceof_;
    }

    private function isBooleanOperatorExpression(Node $expr): bool
    {
        foreach (self::BOOLEAN_OPERATOR_NODES as $booleanOperatorClass) {
            if ($expr instanceof $booleanOperatorClass) {
                return true;
            }
        }

        return false;
    }

    private function isBooleanLikeNamedExpression(Node $expr): bool
    {
        $name = $this->extractNameFromExpression($expr);
        if ($name === null) {
            return false;
        }

        return $this->isBooleanLikeName($name);
    }

    private function extractNameFromExpression(Node $expr): ?string
    {
        return $this->extractVariableLikeName($expr)
            ?? $this->extractPropertyLikeName($expr)
            ?? $this->extractCallLikeName($expr);
    }

    private function extractVariableLikeName(Node $expr): ?string
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return $expr->name;
        }

        return null;
    }

    private function extractPropertyLikeName(Node $expr): ?string
    {
        if ($expr instanceof Expr\PropertyFetch && $expr->name instanceof Node\Identifier) {
            return $expr->name->toString();
        }
        if ($expr instanceof Expr\StaticPropertyFetch && $expr->name instanceof Node\VarLikeIdentifier) {
            return $expr->name->toString();
        }

        return null;
    }

    private function extractCallLikeName(Node $expr): ?string
    {
        if ($expr instanceof Expr\FuncCall && $expr->name instanceof Node\Name) {
            return $expr->name->toString();
        }

        return $this->extractMethodLikeName($expr) ?? $this->extractStaticCallName($expr);
    }

    private function extractMethodLikeName(Node $expr): ?string
    {
        if (($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall)
            && $expr->name instanceof Node\Identifier) {
            return $expr->name->toString();
        }

        return null;
    }

    private function extractStaticCallName(Node $expr): ?string
    {
        if ($expr instanceof Expr\StaticCall && $expr->name instanceof Node\Identifier) {
            return $expr->name->toString();
        }

        return null;
    }

    private function isBooleanLikeName(string $name): bool
    {
        $normalized = ltrim($name, '$');
        if ($normalized === '') {
            return false;
        }

        if (str_ends_with(strtolower($normalized), '_flag')) {
            return true;
        }

        return preg_match('/^(is|has|can|should)(_|[A-Z]).+$/', $normalized) === 1;
    }
}
