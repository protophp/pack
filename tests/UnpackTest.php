<?php

namespace Proto\Pack\Tests;

use PHPUnit\Framework\TestCase;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;

class UnpackTest extends TestCase
{
    public function testUnpack()
    {
        $unpack = new Unpack();

        $unpack->on('unpack-header', function (PackInterface $pack) {
            $this->assertTrue($pack instanceof PackInterface);
            $this->assertSame(['header-key', 'VALUE'], $pack->getHeader());
        });

        $unpack->on('unpack', function (PackInterface $pack) {
            $this->assertTrue($pack instanceof PackInterface);
            $this->assertSame(['header-key', 'VALUE'], $pack->getHeader());
            $this->assertSame('DATA', $pack->getData());
        });

        $unpack->feed((new Pack())->setHeader(['header-key', 'VALUE'])->setData('DATA')->toString());
    }

    public function testChunkedUnpack()
    {
        $unpack = new Unpack();

        $i = 0;
        $unpack->on('unpack-header', function (PackInterface $pack) use (&$i) {
            $this->assertTrue($pack instanceof PackInterface);
            $this->assertSame(['header-key', "VALUE$i"], $pack->getHeader());
        });

        $unpack->on('unpack', function (PackInterface $pack) use (&$i) {
            $this->assertTrue($pack instanceof PackInterface);
            $this->assertSame(['header-key', "VALUE$i"], $pack->getHeader());
            $this->assertSame("DATA$i", $pack->getData());
            $i++;
        });

        $stream =
            (new Pack())->setHeader(['header-key', 'VALUE0'])->setData('DATA0')->toString() .
            (new Pack())->setHeader(['header-key', 'VALUE1'])->setData('DATA1')->toString() .
            (new Pack())->setHeader(['header-key', 'VALUE2'])->setData('DATA2')->toString() .
            (new Pack())->setHeader(['header-key', 'VALUE3'])->setData('DATA3')->toString();

        // Split stream
        foreach (str_split($stream, 2) as $chunk)
            $unpack->feed($chunk);

        $this->assertEquals(20, $this->getCount());
    }
}