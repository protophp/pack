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

    public function testBoolNullToString()
    {
        // NULL
        $pack = (new Pack())->setHeader(null)->setData(null);
        $this->assertIsString((string)$pack);

        // BOOL
        $pack = (new Pack())->setHeader(true)->setData(false);
        $this->assertIsString((string)$pack);
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

        // float
        $pack = (new Pack())->setHeader(1.9705970746224E-28)->setData(-3.9965788001204E+29);
        $this->assertIsString((string)$pack);

        // double
        $pack = (new Pack())->setHeader(8.5185512186893E+122)->setData(-1.6441203792124E-296);
        $this->assertIsString((string)$pack);
    }

    public function testArrayObjectToString()
    {
        $object = new \stdClass();
        $object->VAR = ['Var', "Obj"];
        $pack = (new Pack())->setHeader([10 => 'test', 'key' => [1 => 'foo', 'bar' => 500]])->setData($object);
        $this->assertIsString((string)$pack);
    }

    public function testStringToString()
    {
        $pack = (new Pack())->setHeader('Foo')->setData('Bar');
        $this->assertIsString((string)$pack);
    }


}