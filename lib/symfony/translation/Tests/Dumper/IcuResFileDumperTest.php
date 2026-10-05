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
use Ecard\Cms\Dependencies\Symfony\Component\Translation\Dumper\IcuResFileDumper;
use Ecard\Cms\Dependencies\Symfony\Component\Translation\MessageCatalogue;

class IcuResFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new IcuResFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/resourcebundle/res/en.res', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
