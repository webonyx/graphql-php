<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class AstFromValueUntypedTest extends TestCase
{
    // Describe: valueFromASTUntyped

    /**
     * @see it('parses simple values')
     */
    public function testParsesSimpleValues(): void
    {
        self::assertTestCase('null', null);
        self::assertTestCase('true', true);
        self::assertTestCase('false', false);
        self::assertTestCase('123', 123);
        self::assertTestCase('123.456', 123.456);
        self::assertTestCase('abc123', 'abc123');
    }

    /**
     * @param mixed                     $expected
     * @param array<string, mixed>|null $variables
     */
    private static function assertTestCase(string $valueText, $expected, ?array $variables = null): void
    {
        self::assertEquals(
            $expected,
            AST::valueFromASTUntyped(Parser::parseValue($valueText), $variables)
        );
    }

    /**
     * @see it('parses lists of values')
     */
    public function testParsesListsOfValues(): void
    {
        self::assertTestCase('[true, false]', [true, false]);
        self::assertTestCase('[true, 123.45]', [true, 123.45]);
        self::assertTestCase('[true, null]', [true, null]);
        self::assertTestCase('[true, ["foo", 1.2]]', [true, ['foo', 1.2]]);
    }

    /**
     * @see it('parses input objects')
     */
    public function testParsesInputObjects(): void
    {
        self::assertTestCase(
            '{ int: 123, bool: false }',
            ['int' => 123, 'bool' => false]
        );

        self::assertTestCase(
            '{ foo: [ { bar: "baz"} ] }',
            ['foo' => [['bar' => 'baz']]]
        );
    }

    /**
     * @see it('parses enum values as plain strings')
     */
    public function testParsesEnumValuesAsPlainStrings(): void
    {
        self::assertTestCase(
            'TEST_ENUM_VALUE',
            'TEST_ENUM_VALUE'
        );

        self::assertTestCase(
            '[TEST_ENUM_VALUE]',
            ['TEST_ENUM_VALUE']
        );
    }

    /**
     * @see it('parses enum values as plain strings')
     */
    public function testParsesVariables(): void
    {
        self::assertTestCase(
            '$testVariable',
            'foo',
            ['testVariable' => 'foo']
        );
        self::assertTestCase(
            '[$testVariable]',
            ['foo'],
            ['testVariable' => 'foo']
        );
        self::assertTestCase(
            '{a:[$testVariable]}',
            ['a' => ['foo']],
            ['testVariable' => 'foo']
        );
        self::assertTestCase(
            '$testVariable',
            null,
            ['testVariable' => null]
        );
        self::assertTestCase(
            '$testVariable',
            null,
            []
        );
        self::assertTestCase(
            '$testVariable',
            null,
            null
        );
    }
}
