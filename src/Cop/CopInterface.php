<?php

declare(strict_types=1);

namespace PHPuboCop\Cop;

use PHPuboCop\Core\Offense;
use PHPuboCop\Core\SourceFile;

interface CopInterface
{
    public function name(): string;

    /** @return list<Offense> */
    public function inspect(SourceFile $file, array $config = []): array;
}
