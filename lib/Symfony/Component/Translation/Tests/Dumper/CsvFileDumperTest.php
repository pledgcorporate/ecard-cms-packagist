<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ecard\Cms\Dependencies\Symfony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Ecard\Cms\Dependencies\Symfony\Component\Translation\Dumper\CsvFileDumper;
use Ecard\Cms\Dependencies\Symfony\Component\Translation\MessageCatalogue;

class CsvFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar', 'bar' => 'foo
foo', 'foo;foo' => 'bar']);

        $dumper = new CsvFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/valid.csv', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
