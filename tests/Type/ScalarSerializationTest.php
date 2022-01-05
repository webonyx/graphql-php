<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use function acos;
use GraphQL\Error\SerializationError;
use GraphQL\Tests\Type\TestClasses\ObjectIdStub;
use GraphQL\Type\Definition\Type;
use function log;
use PHPUnit\Framework\TestCase;
use stdClass;

class ScalarSerializationTest extends TestCase
{
    // Type System: Scalar coercion

    /**
     * @see it('serializes output as Int')
     */
    public function testSerializesOutputAsInt(): void
    {
        $intType = Type::int();

        self::assertSame(1, $intType->serialize(1));
        self::assertSame(123, $intType->serialize('123'));
        self::assertSame(0, $intType->serialize(0));
        self::assertSame(-1, $intType->serialize(-1));
        self::assertSame(100000, $intType->serialize(1e5));
        self::assertSame(0, $intType->serialize(0e5));
        self::assertSame(0, $intType->serialize(false));
        self::assertSame(1, $intType->serialize(true));
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public function badIntValues(): iterable
    {
        return [
            [0.1, 'Int cannot represent non-integer value: 0.1'],
            [1.1, 'Int cannot represent non-integer value: 1.1'],
            [-1.1, 'Int cannot represent non-integer value: -1.1'],
            ['-1.1', 'Int cannot represent non-integer value: -1.1'],
            [9876504321, 'Int cannot represent non 32-bit signed integer value: 9876504321'],
            [-9876504321, 'Int cannot represent non 32-bit signed integer value: -9876504321'],
            [1e100, 'Int cannot represent non 32-bit signed integer value: 1.0E+100'],
            [-1e100, 'Int cannot represent non 32-bit signed integer value: -1.0E+100'],
            [log(0), 'Int cannot represent non 32-bit signed integer value: -INF'],
            [acos(8), 'Int cannot represent non-integer value: NAN'],
            ['one', 'Int cannot represent non-integer value: one'],
            ['', 'Int cannot represent non-integer value: (empty string)'],
            [[5], 'Int cannot represent non-integer value: [5]'],
        ];
    }

    /**
     * @param mixed $value
     *
     * @dataProvider badIntValues
     */
    public function testSerializesOutputAsIntErrors($value, string $expectedError): void
    {
        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        $intType = Type::int();

        $this->expectException(SerializationError::class);
        $this->expectExceptionMessage($expectedError);
        $intType->serialize($value);
    }

    /**
     * @see it('serializes output as Float')
     */
    public function testSerializesOutputAsFloat(): void
    {
        $floatType = Type::float();

        self::assertSame(1.0, $floatType->serialize(1));
        self::assertSame(0.0, $floatType->serialize(0));
        self::assertSame(123.5, $floatType->serialize('123.5'));
        self::assertSame(-1.0, $floatType->serialize(-1));
        self::assertSame(0.1, $floatType->serialize(0.1));
        self::assertSame(1.1, $floatType->serialize(1.1));
        self::assertSame(-1.1, $floatType->serialize(-1.1));
        self::assertSame(-1.1, $floatType->serialize('-1.1'));
        self::assertSame(0.0, $floatType->serialize(false));
        self::assertSame(1.0, $floatType->serialize(true));
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public function badFloatValues(): iterable
    {
        return [
            ['one', 'Float cannot represent non numeric value: one'],
            ['', 'Float cannot represent non numeric value: (empty string)'],
            [log(0), 'Float cannot represent non numeric value: -INF'],
            [acos(8), 'Float cannot represent non numeric value: NAN'],
            [[5], 'Float cannot represent non numeric value: [5]'],
        ];
    }

    /**
     * @param mixed $value
     *
     * @dataProvider badFloatValues
     */
    public function testSerializesOutputFloatErrors($value, string $expectedError): void
    {
        $floatType = Type::float();

        $this->expectException(SerializationError::class);
        $this->expectExceptionMessage($expectedError);
        $floatType->serialize($value);
    }

    /**
     * @see it('serializes output as String')
     */
    public function testSerializesOutputAsString(): void
    {
        $stringType = Type::string();
        self::assertSame('string', $stringType->serialize('string'));
        self::assertSame('1', $stringType->serialize(1));
        self::assertSame('-1.1', $stringType->serialize(-1.1));
        self::assertSame('1', $stringType->serialize(true));
        self::assertSame('', $stringType->serialize(false));
        self::assertSame('', $stringType->serialize(null));
        self::assertSame('foo', $stringType->serialize(new class() {
            public function __toString(): string
            {
                return 'foo';
            }
        }));
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public function badStringValues(): iterable
    {
        return [
            [[1], 'String cannot represent value: [1]'],
            [new stdClass(), 'String cannot represent value: instance of stdClass'],
        ];
    }

    /**
     * @param mixed $value
     *
     * @dataProvider badStringValues
     */
    public function testSerializesOutputStringErrors($value, string $expectedError): void
    {
        $stringType = Type::string();

        $this->expectException(SerializationError::class);
        $this->expectExceptionMessage($expectedError);
        $stringType->serialize($value);
    }

    /**
     * @see it('serializes output as Boolean')
     */
    public function testSerializesOutputAsBoolean(): void
    {
        $boolType = Type::boolean();

        self::assertTrue($boolType->serialize(true));
        self::assertTrue($boolType->serialize(1));
        self::assertTrue($boolType->serialize('1'));
        self::assertTrue($boolType->serialize('string'));

        self::assertFalse($boolType->serialize(false));
        self::assertFalse($boolType->serialize(0));
        self::assertFalse($boolType->serialize('0'));
        self::assertFalse($boolType->serialize(''));
    }

    /**
     * @see it('serializes output as ID')
     */
    public function testSerializesOutputAsID(): void
    {
        $idType = Type::id();

        self::assertSame('string', $idType->serialize('string'));
        self::assertSame('false', $idType->serialize('false'));
        self::assertSame('', $idType->serialize(''));
        self::assertSame('1', $idType->serialize('1'));
        self::assertSame('0', $idType->serialize('0'));
        self::assertSame('1', $idType->serialize(1));
        self::assertSame('0', $idType->serialize(0));
        self::assertSame('2', $idType->serialize(new ObjectIdStub(2)));
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public function badIDValues(): iterable
    {
        return [
            [new stdClass(), 'ID cannot represent a non-string and non-integer value: instance of stdClass'],
            [true, 'ID cannot represent a non-string and non-integer value: true'],
            [false, 'ID cannot represent a non-string and non-integer value: false'],
            [-1.1, 'ID cannot represent a non-string and non-integer value: -1.1'],
            [['abc'], 'ID cannot represent a non-string and non-integer value: ["abc"]'],
        ];
    }

    /**
     * @param mixed $value
     *
     * @dataProvider badIDValues
     */
    public function testSerializesOutputAsIDError($value, string $expectedError): void
    {
        $idType = Type::id();

        $this->expectExceptionObject(new SerializationError(
            $expectedError
        ));
        $idType->serialize($value);
    }
}
