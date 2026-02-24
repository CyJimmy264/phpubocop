<?php

declare(strict_types=1);

namespace PHPuboCop\Formatter;

use PHPuboCop\Core\Offense;

interface FormatterInterface
{
    /** @param list<Offense> $offenses */
    public function format(array $offenses): string;
}
