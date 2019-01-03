<?php

namespace Proto\Pack\Tests;

use PHPUnit\Framework\TestCase;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;

class PackTest extends TestCase
{
    public function testSetData()
    {
        $pack = new Pack();

        $this->assertFalse($pack->isData());
        $this->assertTrue($pack->setData('TestData') instanceof PackInterface);
        $this->assertSame('TestData', $pack->getData());
        $this->assertTrue($pack->isData());
    }

    public function testSetHeader()
    {
        $pack = new Pack();

        $this->assertFalse($pack->isHeader());
        $this->assertTrue($pack->setHeader('TestHeader') instanceof PackInterface);
        $this->assertSame('TestHeader', $pack->getHeader());
        $this->assertTrue($pack->isHeader());
    }

    public function testIntToString()
    {
        // int8
        $pack = (new Pack())->setHeader(240)->setData(-100);
        $this->assertIsString((string)$pack);

        // int16
        $pack = (new Pack())->setHeader(-18321)->setData(52221);
        $this->assertIsString((string)$pack);

        // int32
        $pack = (new Pack())->setHeader(3275862454)->setData(-1865131667);
        $this->assertIsString((string)$pack);

        // int 64
        $pack = (new Pack())->setHeader(-3344407726397714395)->setData(5346531524877826330);
        $this->assertIsString((string)$pack);
    }

}