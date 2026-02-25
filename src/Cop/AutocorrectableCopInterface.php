<?php

declare(strict_types=1);

namespace PHPuboCop\Cop;

use PHPuboCop\Core\SourceFile;

interface AutocorrectableCopInterface
{
    public function autocorrect(SourceFile $file, array $config = []): string;
}
