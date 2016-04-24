<?php
namespace GraphQL\Tests;

use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\ScalarTypeDefinition;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;

class SchemaPrinterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it prints minimal ast
     */
    public function testPrintsMinimalAst()
    {
        $ast = new ScalarTypeDefinition([
            'name' => new Name(['value' => 'foo'])
        ]);
        $this->assertEquals('scalar foo', Printer::doPrint($ast));
    }

    /**
     * @it produces helpful error messages
     */
    public function testProducesHelpfulErrorMessages()
    {
        // $badAst1 = { random: 'Data' };
        $badAst = (object) ['random' => 'Data'];
        $this->setExpectedException('Exception', 'Invalid AST Node: {"random":"Data"}');
        Printer::doPrint($badAst);
    }

    /**
     * @it does not alter ast
     */
    public function testDoesNotAlterAst()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast = Parser::parse($kitchenSink);
        $astCopy = $ast->cloneDeep();
        Printer::doPrint($ast);

        $this->assertEquals($astCopy, $ast);
    }

    public function testPrintsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $ast = Parser::parse($kitchenSink);
        $printed = Printer::doPrint($ast);

        $expected = 'schema {
  query: QueryType
  mutation: MutationType
}

type Foo implements Bar {
  one: Type
  two(argument: InputType!): Type
  three(argument: InputType, other: String): Int
  four(argument: String = "string"): String
  five(argument: [String] = ["string", "string"]): String
  six(argument: InputType = {key: "value"}): Type
}

interface Bar {
  one: Type
  four(argument: String = "string"): String
}

union Feed = Story | Article | Advert

scalar CustomScalar

enum Site {
  DESKTOP
  MOBILE
}

input InputType {
  key: String!
  answer: Int = 42
}

extend type Foo {
  seven(argument: [String]): Type
}

directive @skip(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
';
        $this->assertEquals($expected, $printed);
    }
}
