<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

class AstFromValueUntypedTest extends TestCase
{
    // Describe: valueFromASTUntyped

    private function assertTestCase($valueText, $expected, array $variables = null) {
        $this->assertEquals(
            $expected,
            AST::valueFromASTUntyped(Parser::parseValue($valueText), $variables)
        );
    }

    /**
     * @it parses simple values
     */
    public function testParsesSimpleValues()
    {
        $this->assertTestCase('null', null);
        $this->assertTestCase('true', true);
        $this->assertTestCase('false', false);
        $this->assertTestCase('123', 123);
        $this->assertTestCase('123.456', 123.456);
        $this->assertTestCase('abc123', 'abc123');
    }

    /**
     * @it parses lists of values
     */
    public function testParsesListsOfValues()
    {
        $this->assertTestCase('[true, false]', [true, false]);
        $this->assertTestCase('[true, 123.45]', [true, 123.45]);
        $this->assertTestCase('[true, null]', [true, null]);
        $this->assertTestCase('[true, ["foo", 1.2]]', [true, ['foo', 1.2]]);
    }

    /**
     * @it parses input objects
     */
    public function testParsesInputObjects()
    {
        $this->assertTestCase(
            '{ int: 123, bool: false }',
            ['int' => 123, 'bool' => false]
        );

        $this->assertTestCase(
            '{ foo: [ { bar: "baz"} ] }',
            ['foo' => [['bar' => 'baz']]]
        );
    }

    /**
     * @it parses enum values as plain strings
     */
    public function testParsesEnumValuesAsPlainStrings()
    {
        $this->assertTestCase(
            'TEST_ENUM_VALUE',
            'TEST_ENUM_VALUE'
        );

        $this->assertTestCase(
            '[TEST_ENUM_VALUE]',
            ['TEST_ENUM_VALUE']
        );
    }

    /**
     * @it parses enum values as plain strings
     */
    public function testParsesVariables()
    {
        $this->assertTestCase(
            '$testVariable',
            'foo',
            ['testVariable' => 'foo']
        );
        $this->assertTestCase(
            '[$testVariable]',
            ['foo'],
            ['testVariable' => 'foo']
        );
        $this->assertTestCase(
            '{a:[$testVariable]}',
            ['a' => ['foo']],
            ['testVariable' => 'foo']
        );
        $this->assertTestCase(
            '$testVariable',
            null,
            ['testVariable' => null]
        );
        $this->assertTestCase(
            '$testVariable',
            null,
            []
        );
        $this->assertTestCase(
            '$testVariable',
            null,
            null
        );
    }
}
