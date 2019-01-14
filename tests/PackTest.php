<?php

namespace Proto\Pack\Tests;

use PHPUnit\Framework\TestCase;
use Proto\Pack\Pack;
use Proto\Pack\PackException;
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

    public function testArrayHeader()
    {
        $pack = new Pack();

        $this->assertFalse($pack->isHeader());
        $this->assertTrue($pack->setHeaderByKey('NAME', 'VALUE') instanceof PackInterface);
        $this->assertNull($pack->getHeaderByKey('NO-NAME'));
        $this->assertSame('VALUE', $pack->getHeaderByKey('NAME'));
        $this->assertTrue($pack->isHeader());
    }

    public function testHeaderLimited()
    {
        try {
            $this->PackTest(true, null);
        } catch (PackException $e) {
            $this->assertEquals($e->getCode(), PackException::ERR_HEADER_UNSUPPORTED_TYPES);
        }

        try {
            $this->PackTest(false, null);
        } catch (PackException $e) {
            $this->assertEquals($e->getCode(), PackException::ERR_HEADER_UNSUPPORTED_TYPES);
        }

        try {
            $this->PackTest(unpack('f', random_bytes(4))[1], null);
        } catch (PackException $e) {
            $this->assertEquals($e->getCode(), PackException::ERR_HEADER_UNSUPPORTED_TYPES);
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        try {
            $this->PackTest(unpack('d', random_bytes(8))[1], null);
        } catch (PackException $e) {
            $this->assertEquals($e->getCode(), PackException::ERR_HEADER_UNSUPPORTED_TYPES);
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        try {
            $this->PackTest(random_bytes(0xFFFF + 1), null);
        } catch (PackException $e) {
            $this->assertEquals($e->getCode(), PackException::ERR_HEADER_TOO_LARGE);
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        $this->assertEquals(5, $this->getCount());
    }

    public function testNullPack()
    {
        $this->PackTest(null, null);
    }

    public function testBoolPack()
    {
        $this->PackTest(null, false);
    }

    public function testInt8Pack()
    {
        $this->PackTest(240, -100);
    }

    public function testInt16Pack()
    {
        $this->PackTest(-18321, 52221);
    }

    public function testInt32Pack()
    {
        $this->PackTest(3275862454, -1865131667);
    }

    public function testInt64Pack()
    {
        $this->PackTest(-3344407726397714395, 5346531524877826330);
    }

    public function testFloatPack()
    {
        $this->PackTest(null, -3.9965788001203555E+29);
        $this->PackTest(null, 1.9705970746224E-28);
    }

    public function testDoublePack()
    {
        $this->PackTest(null, -1.6441203792124E-296);
        $this->PackTest(null, 8.5185512186893E+122);
    }

    public function testArrayObjectPack()
    {
        $object = new \stdClass();
        $object->VAR = ['Var', "Obj"];
        $this->PackTest([10 => 'test', 'key' => [1 => 'foo', 'bar' => 500]], $object);
    }

    public function testStringPack()
    {
        $this->PackTest('Foo', 'Bar');
    }

    private function PackTest($header, $data)
    {
        $pack = (new Pack())->setHeader($header)->setData($data);
        $encoded = $pack->toString();

        $this->assertIsString($encoded);
        $cPack = new Pack();

        // In one
        $cPack->mergeFrom($encoded);

        if (is_object($header))
            $this->assertSame(serialize($header), serialize($cPack->getHeader()));
        else
            $this->assertSame($header, $cPack->getHeader());

        if (is_object($data))
            $this->assertSame(serialize($data), serialize($cPack->getData()));
        else
            $this->assertSame($data, $cPack->getData());

        // Split
        $sPack = new Pack();
        foreach (str_split($encoded) as $chunk)
            $sPack->mergeFrom($chunk);


        if (is_object($header))
            $this->assertSame(serialize($header), serialize($cPack->getHeader()));
        else
            $this->assertSame($header, $cPack->getHeader());

        if (is_object($data))
            $this->assertSame(serialize($data), serialize($cPack->getData()));
        else
            $this->assertSame($data, $cPack->getData());
    }
}