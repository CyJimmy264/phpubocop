<?php

declare(strict_types=1);

namespace PHPuboCop\Cop\Security;

use PHPuboCop\Cop\CopInterface;
use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;
use PHPuboCop\Util\AstWalker;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

final class ExecCop implements CopInterface
{
    private const DANGEROUS_FUNCTIONS = [
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
        'popen',
    ];

    public function name(): string
    {
        return 'Security/Exec';
    }

    public function inspect(SourceFile $file, array $config = []): array
    {
        $offenses = [];

        AstWalker::walk($file->ast(), function (Node $node) use (&$offenses, $file): void {
            if (!$node instanceof Expr\FuncCall || !$node->name instanceof Name) {
                return;
            }

            $name = strtolower($node->name->toString());
            if (!in_array($name, self::DANGEROUS_FUNCTIONS, true)) {
                return;
            }

            $offenses[] = new Offense(
                $this->name(),
                $file->path,
                (int) $node->getStartLine(),
                1,
                sprintf('Avoid %s(). It can introduce command injection risks.', $name),
                'warning'
            );
        });

        return $offenses;
    }
}
