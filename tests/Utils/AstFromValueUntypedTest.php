<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

class AstFromValueUntypedTest extends TestCase
{
    // Describe: valueFromASTUntyped
    /**
     * @see it('parses simple values')
     */
    public function testParsesSimpleValues() : void
    {
        $this->assertTestCase('null', null);
        $this->assertTestCase('true', true);
        $this->assertTestCase('false', false);
        $this->assertTestCase('123', 123);
        $this->assertTestCase('123.456', 123.456);
        $this->assertTestCase('abc123', 'abc123');
    }

    /**
     * @param mixed[]|null $variables
     */
    private function assertTestCase($valueText, $expected, ?array $variables = null) : void
    {
        $this->assertEquals(
            $expected,
            AST::valueFromASTUntyped(Parser::parseValue($valueText), $variables)
        );
    }

    /**
     * @see it('parses lists of values')
     */
    public function testParsesListsOfValues() : void
    {
        $this->assertTestCase('[true, false]', [true, false]);
        $this->assertTestCase('[true, 123.45]', [true, 123.45]);
        $this->assertTestCase('[true, null]', [true, null]);
        $this->assertTestCase('[true, ["foo", 1.2]]', [true, ['foo', 1.2]]);
    }

    /**
     * @see it('parses input objects')
     */
    public function testParsesInputObjects() : void
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
     * @see it('parses enum values as plain strings')
     */
    public function testParsesEnumValuesAsPlainStrings() : void
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
     * @see it('parses enum values as plain strings')
     */
    public function testParsesVariables() : void
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
