<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Style;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;

final class DoubleQuotesCop implements CopInterface
{
    public function name(): string
    {
        return 'Style/DoubleQuotes';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof String_) {
                return;
            }

            $kind = $node->getAttribute('kind');
            if ($kind !== String_::KIND_DOUBLE_QUOTED) {
                return;
            }

            $rawLiteral = $this->rawLiteral($file->content, $node);
            if ($rawLiteral !== null && str_contains($rawLiteral, '\\')) {
                return;
            }

            if (str_contains($node->value, "'")) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                'Prefer single-quoted strings when interpolation is not needed.'
            );
        });

        return $offenses;
    }

    private function rawLiteral(string $content, String_ $node): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if (!is_int($start) || !is_int($end) || $end < $start) {
            return null;
        }

        return substr($content, $start, $end - $start + 1) ?: null;
    }
}
