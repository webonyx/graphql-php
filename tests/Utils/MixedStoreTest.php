<?php
namespace GraphQL\Tests\Utils;


use GraphQL\Utils\Utils;
use GraphQL\Utils\MixedStore;

class MixedStoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MixedStore
     */
    private $mixedStore;

    public function setUp()
    {
        $this->mixedStore = new MixedStore();
    }

    public function getPossibleValues()
    {
        return [
            null,
            false,
            true,
            '',
            '0',
            '1',
            'a',
            [],
            new \stdClass(),
            function() {},
            new MixedStore()
        ];
    }

    public function testAcceptsNullKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(null, $value);
        }
    }

    public function testAcceptsBoolKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(false, $value);
        }
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(true, $value);
        }
    }

    public function testAcceptsIntKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000, $value);
            $this->assertAcceptsKeyValue(-1, $value);
            $this->assertAcceptsKeyValue(0, $value);
            $this->assertAcceptsKeyValue(1, $value);
            $this->assertAcceptsKeyValue(1000000, $value);
        }
    }

    public function testAcceptsFloatKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000.5, $value);
            $this->assertAcceptsKeyValue(-1.6, $value);
            $this->assertAcceptsKeyValue(-0.0001, $value);
            $this->assertAcceptsKeyValue(0.0000, $value);
            $this->assertAcceptsKeyValue(0.0001, $value);
            $this->assertAcceptsKeyValue(1.6, $value);
            $this->assertAcceptsKeyValue(1000000.5, $value);
        }
    }

    public function testAcceptsArrayKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue([], $value);
            $this->assertAcceptsKeyValue([null], $value);
            $this->assertAcceptsKeyValue([[]], $value);
            $this->assertAcceptsKeyValue([new \stdClass()], $value);
            $this->assertAcceptsKeyValue(['a', 'b'], $value);
            $this->assertAcceptsKeyValue(['a' => 'b'], $value);
        }
    }

    public function testAcceptsObjectKeys()
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(new \stdClass(), $value);
            $this->assertAcceptsKeyValue(new MixedStore(), $value);
            $this->assertAcceptsKeyValue(function() {}, $value);
        }
    }

    private function assertAcceptsKeyValue($key, $value)
    {
        $err = 'Failed assertion that MixedStore accepts key ' .
            Utils::printSafe($key) . ' with value ' .  Utils::printSafe($value);

        $this->assertFalse($this->mixedStore->offsetExists($key), $err);
        $this->mixedStore->offsetSet($key, $value);
        $this->assertTrue($this->mixedStore->offsetExists($key), $err);
        $this->assertSame($value, $this->mixedStore->offsetGet($key), $err);
        $this->mixedStore->offsetUnset($key);
        $this->assertFalse($this->mixedStore->offsetExists($key), $err);
        $this->assertProvidesArrayAccess($key, $value);
    }

    private function assertProvidesArrayAccess($key, $value)
    {
        $err = 'Failed assertion that MixedStore provides array access for key ' .
            Utils::printSafe($key) . ' with value ' .  Utils::printSafe($value);

        $this->assertFalse(isset($this->mixedStore[$key]), $err);
        $this->mixedStore[$key] = $value;
        $this->assertTrue(isset($this->mixedStore[$key]), $err);
        $this->assertEquals(!empty($value), !empty($this->mixedStore[$key]), $err);
        $this->assertSame($value, $this->mixedStore[$key], $err);
        unset($this->mixedStore[$key]);
        $this->assertFalse(isset($this->mixedStore[$key]), $err);
    }
}
