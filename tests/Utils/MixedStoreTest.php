<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Utils\MixedStore;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

final class MixedStoreTest extends TestCase
{
    /** @var MixedStore<mixed> */
    private MixedStore $mixedStore;

    public function setUp(): void
    {
        $this->mixedStore = new MixedStore();
    }

    public function testAcceptsNullKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(null, $value);
        }
    }

    /** @return array<int, mixed> */
    public function possibleValues(): array
    {
        /** @var MixedStore<mixed> $mixedStore */
        $mixedStore = new MixedStore();

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
            static function (): void {},
            $mixedStore,
        ];
    }

    /**
     * @param mixed $key   anything goes
     * @param mixed $value anything goes
     *
     * @throws \InvalidArgumentException
     */
    private function assertAcceptsKeyValue($key, $value): void
    {
        $safeKey = Utils::printSafe($key);
        $safeValue = Utils::printSafe($value);
        $message = "Failed assertion that MixedStore accepts key {$safeKey} with value {$safeValue}";

        self::assertFalse($this->mixedStore->offsetExists($key), $message);

        $this->mixedStore->offsetSet($key, $value);
        self::assertTrue($this->mixedStore->offsetExists($key), $message);
        self::assertSame($value, $this->mixedStore->offsetGet($key), $message);

        $this->mixedStore->offsetUnset($key);
        self::assertFalse($this->mixedStore->offsetExists($key), $message);

        $this->assertProvidesArrayAccess($key, $value);
    }

    /**
     * @param mixed $key   anything goes
     * @param mixed $value anything goes
     *
     * @throws \InvalidArgumentException
     */
    private function assertProvidesArrayAccess($key, $value): void
    {
        $safeKey = Utils::printSafe($key);
        $safeValue = Utils::printSafe($value);
        $err = "Failed assertion that MixedStore provides array access for key {$safeKey} with value {$safeValue}";

        self::assertFalse(isset($this->mixedStore[$key]), $err);
        $this->mixedStore[$key] = $value;
        self::assertTrue(isset($this->mixedStore[$key]), $err);
        self::assertEquals((bool) $value, (bool) $this->mixedStore[$key], $err);
        self::assertSame($value, $this->mixedStore[$key], $err);
        unset($this->mixedStore[$key]);
        self::assertFalse(isset($this->mixedStore[$key]), $err);
    }

    public function testAcceptsBoolKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(false, $value);
        }

        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(true, $value);
        }
    }

    public function testAcceptsIntKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000, $value);
            $this->assertAcceptsKeyValue(-1, $value);
            $this->assertAcceptsKeyValue(0, $value);
            $this->assertAcceptsKeyValue(1, $value);
            $this->assertAcceptsKeyValue(1000000, $value);
        }
    }

    public function testAcceptsFloatKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000.5, $value);
            $this->assertAcceptsKeyValue(-1.6, $value);
            $this->assertAcceptsKeyValue(-0.0001, $value);
            $this->assertAcceptsKeyValue(0.0000, $value);
            $this->assertAcceptsKeyValue(0.0001, $value);
            $this->assertAcceptsKeyValue(1.6, $value);
            $this->assertAcceptsKeyValue(1000000.5, $value);
        }
    }

    public function testAcceptsArrayKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue([], $value);
            $this->assertAcceptsKeyValue([null], $value);
            $this->assertAcceptsKeyValue([[]], $value);
            $this->assertAcceptsKeyValue([new \stdClass()], $value);
            $this->assertAcceptsKeyValue(['a', 'b'], $value);
            $this->assertAcceptsKeyValue(['a' => 'b'], $value);
        }
    }

    public function testAcceptsObjectKeys(): void
    {
        foreach ($this->possibleValues() as $value) {
            $this->assertAcceptsKeyValue(new \stdClass(), $value);
            $this->assertAcceptsKeyValue(new MixedStore(), $value);
            $this->assertAcceptsKeyValue(
                static function (): void {},
                $value
            );
        }
    }
}
