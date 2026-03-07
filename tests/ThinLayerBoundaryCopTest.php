<?php

declare(strict_types=1);

namespace PHPuboCop\Tests;

use PHPuboCop\Cop\Architecture\ThinLayerBoundaryCop;
use PHPuboCop\Core\SourceFile;
use PHPUnit\Framework\TestCase;

final class ThinLayerBoundaryCopTest extends TestCase
{
    public function testBoundaryCopProducesNoOffenses(): void
    {
        $cop = new ThinLayerBoundaryCop();
        $source = new SourceFile(
            '/tmp/project/www_data/ajax/order.php',
            "<?php\nif (\$a) {}\nif (\$b) {}\nif (\$c) {}\nmysql_query('SELECT 1');\n",
        );

        $offenses = $cop->inspect($source);

        self::assertCount(0, $offenses);
    }
}
