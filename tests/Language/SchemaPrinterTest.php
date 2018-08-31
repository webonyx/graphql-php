<?php
namespace GraphQL\Tests;

use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use PHPUnit\Framework\TestCase;
use Throwable;

class SchemaPrinterTest extends TestCase
{
    /**
     * @see it('prints minimal ast')
     */
    public function testPrintsMinimalAst()
    {
        $ast = new ScalarTypeDefinitionNode([
            'name' => new NameNode(['value' => 'foo'])
        ]);
        $this->assertEquals('scalar foo', Printer::doPrint($ast));
    }

    /**
     * @see it('produces helpful error messages')
     */
    public function testProducesHelpfulErrorMessages()
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Invalid AST Node: {"random":"Data"}');

        // $badAst1 = { random: 'Data' };
        $badAst = (object) ['random' => 'Data'];
        Printer::doPrint($badAst);
    }

    /**
     * @see it('does not alter ast')
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

"""
This is a description
of the `Foo` type.
"""
type Foo implements Bar & Baz {
  one: Type
  two(argument: InputType!): Type
  three(argument: InputType, other: String): Int
  four(argument: String = "string"): String
  five(argument: [String] = ["string", "string"]): String
  six(argument: InputType = {key: "value"}): Type
  seven(argument: Int = null): Type
}

type AnnotatedObject @onObject(arg: "value") {
  annotatedField(arg: Type = "default" @onArg): Type @onField
}

type UndefinedType

extend type Foo {
  seven(argument: [String]): Type
}

extend type Foo @onType

interface Bar {
  one: Type
  four(argument: String = "string"): String
}

interface AnnotatedInterface @onInterface {
  annotatedField(arg: Type @onArg): Type @onField
}

interface UndefinedInterface

extend interface Bar {
  two(argument: InputType!): Type
}

extend interface Bar @onInterface

union Feed = Story | Article | Advert

union AnnotatedUnion @onUnion = A | B

union AnnotatedUnionTwo @onUnion = A | B

union UndefinedUnion

extend union Feed = Photo | Video

extend union Feed @onUnion

scalar CustomScalar

scalar AnnotatedScalar @onScalar

extend scalar CustomScalar @onScalar

enum Site {
  DESKTOP
  MOBILE
}

enum AnnotatedEnum @onEnum {
  ANNOTATED_VALUE @onEnumValue
  OTHER_VALUE
}

enum UndefinedEnum

extend enum Site {
  VR
}

extend enum Site @onEnum

input InputType {
  key: String!
  answer: Int = 42
}

input AnnotatedInput @onInputObject {
  annotatedField: Type @onField
}

input UndefinedInput

extend input InputType {
  other: Float = 1.23e4
}

extend input InputType @onInputObject

directive @skip(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

directive @include2(if: Boolean!) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
';
        $this->assertEquals($expected, $printed);
    }
}
