<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\AutocorrectableCopInterface;
use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Cop\SafeAutocorrectableCopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

final class StrictComparisonCop implements CopInterface, AutocorrectableCopInterface, SafeAutocorrectableCopInterface
{
    public function name(): string
    {
        return 'Style/StrictComparison';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if ($node instanceof Expr\BinaryOp\Equal) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Prefer strict comparison (===) over ==.'
                );
                return;
            }

            if ($node instanceof Expr\BinaryOp\NotEqual) {
                $offenses[] = new Offense(
                    $this->name(),
                    $file->path,
                    (int) $node->getStartLine(),
                    1,
                    'Prefer strict comparison (!==) over !=.'
                );
            }
        });

        return $offenses;
    }

    public function autocorrect(SourceFile $file, array $config = []): string
    {
        $replacements = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$replacements, $file): void {
            if (!$this->isLooseComparison($node) || !$this->isSafeAutocorrectComparison($node)) {
                return;
            }

            $leftEnd = $node->left->getEndFilePos();
            $rightStart = $node->right->getStartFilePos();
            if (!is_int($leftEnd) || !is_int($rightStart) || $rightStart <= $leftEnd) {
                return;
            }

            $between = substr($file->content, $leftEnd + 1, $rightStart - $leftEnd - 1);
            if (!is_string($between) || $between === '') {
                return;
            }

            if (preg_match('/(!=|==)/', $between, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return;
            }

            $operator = $matches[1][0];
            $offset = (int) $matches[1][1];
            $replacement = $operator === '==' ? '===' : '!==';

            $replacements[] = [
                'start' => $leftEnd + 1 + $offset,
                'length' => strlen($operator),
                'replacement' => $replacement,
            ];
        });

        if ($replacements === []) {
            return $file->content;
        }

        usort($replacements, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        $content = $file->content;
        foreach ($replacements as $replacement) {
            $content = substr($content, 0, $replacement['start'])
                . $replacement['replacement']
                . substr($content, $replacement['start'] + $replacement['length']);
        }

        return $content;
    }

    private function isLooseComparison(Node $node): bool
    {
        return $node instanceof Expr\BinaryOp\Equal || $node instanceof Expr\BinaryOp\NotEqual;
    }

    private function isSafeAutocorrectComparison(Node $node): bool
    {
        $leftType = $this->literalType($node->left);
        $rightType = $this->literalType($node->right);

        return $leftType !== null && $leftType === $rightType;
    }

    private function literalType(Node $node): ?string
    {
        if ($node instanceof Scalar\Int_) {
            return 'int';
        }

        if ($node instanceof Scalar\Float_) {
            return 'float';
        }

        if ($node instanceof Scalar\String_) {
            return 'string';
        }

        if ($node instanceof Expr\ConstFetch) {
            $name = strtolower($node->name->toString());
            if (in_array($name, ['true', 'false'], true)) {
                return 'bool';
            }

            if ($name === 'null') {
                return 'null';
            }
        }

        return null;
    }
}
