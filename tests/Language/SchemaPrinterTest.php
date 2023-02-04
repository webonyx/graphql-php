<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;
use function Safe\json_encode;

/**
 * @see describe('Printer: SDL document')
 */
final class SchemaPrinterTest extends TestCase
{
    /**
     * @see it('prints minimal ast')
     */
    public function testPrintsMinimalAst(): void
    {
        $ast = new ScalarTypeDefinitionNode([
            'name' => new NameNode(['value' => 'foo']),
            'directives' => new NodeList([]),
        ]);
        self::assertEquals('scalar foo', Printer::doPrint($ast));
    }

    /**
     * @see it('produces helpful error messages')
     */
    public function testProducesHelpfulErrorMessages(): void
    {
        self::markTestSkipped('Not equivalent to the reference implementation because we have runtime types that fail early');
    }

    /**
     * @see it('prints kitchen sink without altering ast', () => {
     */
    public function testPrintsKitchenSinkWithoutAlteringAST(): void
    {
        $ast = Parser::parse(file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql'), ['noLocation' => true]);

        $astBeforePrintCall = json_encode($ast);
        $printed = Printer::doPrint($ast);
        $printedAST = Parser::parse($printed, ['noLocation' => true]);

        self::assertEquals($printedAST, $ast);
        self::assertSame($astBeforePrintCall, json_encode($ast));

        self::assertSame(
            <<<'GRAPHQL'
schema {
  query: QueryType
  mutation: MutationType
}

"""
This is a description
of the `Foo` type.
"""
type Foo implements Bar & Baz & Two {
  one: Type
  """This is a description of the `two` field."""
  two(
    """This is a description of the `argument` argument."""
    argument: InputType!
  ): Type
  three(argument: InputType, other: String): Int
  four(argument: String = "string"): String
  five(argument: [String] = ["string", "string"]): String
  six(argument: InputType = { key: "value" }): Type
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

extend interface Bar implements Two {
  two(argument: InputType!): Type
}

extend interface Bar @onInterface

interface Baz implements Bar & Two {
  one: Type
  two(argument: InputType!): Type
  four(argument: String = "string"): String
}

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

directive @myRepeatableDir(name: String!) repeatable on OBJECT | INTERFACE

GRAPHQL
            ,
            $printed
        );
    }

    /**
     * it('prints viral schema correctly', () => {.
     */
    public function testPrintsViralSchemaCorrectly(): void
    {
        $schemaSDL = \Safe\file_get_contents(__DIR__ . '/../viralSchema.graphql');
        $schema = BuildSchema::build($schemaSDL);
        $this->assertSame($schemaSDL, SchemaPrinter::doPrint($schema));
    }
}
