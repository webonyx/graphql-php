<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type Options from SchemaPrinter
 *
 * @see describe('Type System Printer', () => {
 */
final class SchemaPrinterTest extends TestCase
{
    /**
     * @param Options $options
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws InvariantViolation
     * @throws SerializationError
     */
    private static function assertPrintedSchemaEquals(string $expected, Schema $schema, array $options = []): void
    {
        $printedSchema = SchemaPrinter::doPrint($schema, $options);
        $builtSchema = BuildSchema::build($printedSchema);
        $cycledSchema = SchemaPrinter::doPrint($builtSchema, $options);

        self::assertSame($printedSchema, $cycledSchema);
        self::assertSame($expected, $printedSchema);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     *
     * @throws InvariantViolation
     */
    private function buildSingleFieldSchema(array $fieldConfig): Schema
    {
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => ['singleField' => $fieldConfig],
        ]);

        return new Schema(['query' => $query]);
    }

    /**
     * @see it('Prints String Field')
     */
    public function testPrintsStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints [String] Field')
     */
    public function testPrintArrayStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::listOf(Type::string()),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: [String]
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String! Field')
     */
    public function testPrintNonNullStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::nonNull(Type::string()),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: String!
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints [String]! Field')
     */
    public function testPrintNonNullArrayStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::nonNull(Type::listOf(Type::string())),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: [String]!
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints [String!] Field')
     */
    public function testPrintArrayNonNullStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::listOf(Type::nonNull(Type::string())),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: [String!]
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints [String!]! Field')
     */
    public function testPrintNonNullArrayNonNullStringField(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField: [String!]!
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see https://github.com/webonyx/graphql-php/pull/631
     *
     * @dataProvider deprecationReasonDataProvider
     */
    public function testPrintDeprecatedField(?string $deprecationReason, string $expectedDeprecationDirective): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::int(),
            'deprecationReason' => $deprecationReason,
        ]);
        self::assertPrintedSchemaEquals(
            <<<GRAPHQL
            type Query {
              singleField: Int{$expectedDeprecationDirective}
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public function deprecationReasonDataProvider(): iterable
    {
        yield 'when deprecationReason is null' => [
            null,
            '',
        ];

        yield 'when deprecationReason is empty string' => [
            '',
            ' @deprecated',
        ];

        yield 'when deprecationReason is the default deprecation reason' => [
            Directive::DEFAULT_DEPRECATION_REASON,
            ' @deprecated',
        ];

        yield 'when deprecationReason is not empty string' => [
            'this is deprecated',
            ' @deprecated(reason: "this is deprecated")',
        ];
    }

    /**
     * @see it('Print Object Field')
     */
    public function testPrintObjectField(): void
    {
        $fooType = new ObjectType([
            'name' => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);
        $schema = new Schema(['types' => [$fooType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Foo {
              str: String
            }

            GRAPHQL,
            $schema
        );
    }

    public function testPrintObjectFieldDescriptions(): void
    {
        $fooType = new ObjectType([
            'name' => 'Foo',
            'fields' => [
                'one' => [
                    'type' => Type::string(),
                ],
                'two' => [
                    'type' => Type::string(),
                    'description' => 'This is two',
                ],
                'three' => [
                    'type' => Type::string(),
                    'description' => 'This is three',
                ],
                'four' => [
                    'type' => Type::string(),
                ],
                'five' => [
                    'type' => Type::string(),
                ],
            ],
        ]);
        $schema = new Schema(['types' => [$fooType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Foo {
              one: String

              "This is two"
              two: String

              "This is three"
              three: String

              four: String
              five: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Int Arg')
     */
    public function testPrintsStringFieldWithIntArg(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int()]],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Int Arg With Default')
     */
    public function testPrintsStringFieldWithIntArgWithDefault(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int(), 'defaultValue' => 2]],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int = 2): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With String Arg With Default')
     */
    public function testPrintsStringFieldWithStringArgWithDefault(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::string(), 'defaultValue' => "tes\t de\fault"]],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: String = "tes\t de\fault"): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Int Arg With Default Null')
     */
    public function testPrintsStringFieldWithIntArgWithDefaultNull(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int(), 'defaultValue' => null]],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int = null): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Int! Arg')
     */
    public function testPrintsStringFieldWithNonNullIntArg(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::nonNull(Type::int())]],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int!): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args')
     */
    public function testPrintsStringFieldWithMultipleArgs(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int()],
                'argTwo' => ['type' => Type::string()],
            ],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int, argTwo: String): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, First is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsFirstIsDefault(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int(), 'defaultValue' => 1],
                'argTwo' => ['type' => Type::string()],
                'argThree' => ['type' => Type::boolean()],
            ],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int = 1, argTwo: String, argThree: Boolean): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, Second is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsSecondIsDefault(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int()],
                'argTwo' => ['type' => Type::string(), 'defaultValue' => 'foo'],
                'argThree' => ['type' => Type::boolean()],
            ],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int, argTwo: String = "foo", argThree: Boolean): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, Last is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsLastIsDefault(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int()],
                'argTwo' => ['type' => Type::string()],
                'argThree' => ['type' => Type::boolean(), 'defaultValue' => false],
            ],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(argOne: Int, argTwo: String, argThree: Boolean = false): String
            }

            GRAPHQL,
            $schema
        );
    }

    // TODO it('Prints schema with description', () => {
    // TODO it('Omits schema of common names', () => {

    public function testSplitsArgsWithDescriptionsAcrossMultipleLines(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int(), 'description' => 'This is the first argument'],
                'argTwo' => ['type' => Type::string(), 'description' => 'This is the second argument'],
                'argThree' => ['type' => Type::boolean()],
                'argFour' => ['type' => Type::boolean()],
                'argFive' => ['type' => Type::string(), 'description' => 'This is the fifth argument'],
            ],
        ]);
        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              singleField(
                "This is the first argument"
                argOne: Int

                "This is the second argument"
                argTwo: String

                argThree: Boolean
                argFour: Boolean

                "This is the fifth argument"
                argFive: String
              ): String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints custom query root types')
     */
    public function testPrintsCustomQueryRootTypes(): void
    {
        $schema = new Schema([
            'query' => new ObjectType(['name' => 'CustomType', 'fields' => []]),
        ]);

        $expected = <<<'GRAPHQL'
            schema {
              query: CustomType
            }

            type CustomType

            GRAPHQL;
        self::assertPrintedSchemaEquals($expected, $schema);
    }

    /**
     * @see it('Prints custom mutation root types')
     */
    public function testPrintsCustomMutationRootTypes(): void
    {
        $schema = new Schema([
            'mutation' => new ObjectType(['name' => 'CustomType', 'fields' => []]),
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            schema {
              mutation: CustomType
            }

            type CustomType

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints custom subscription root types')
     */
    public function testPrintsCustomSubscriptionRootTypes(): void
    {
        $schema = new Schema([
            'subscription' => new ObjectType(['name' => 'CustomType', 'fields' => []]),
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            schema {
              subscription: CustomType
            }

            type CustomType

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Interface')
     */
    public function testPrintInterface(): void
    {
        $fooType = new InterfaceType([
            'name' => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => ['str' => ['type' => Type::string()]],
            'interfaces' => [$fooType],
        ]);

        $schema = new Schema(['types' => [$barType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Bar implements Foo {
              str: String
            }

            interface Foo {
              str: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Multiple Interface')
     */
    public function testPrintMultipleInterface(): void
    {
        $fooType = new InterfaceType([
            'name' => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $bazType = new InterfaceType([
            'name' => 'Baz',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => [
                'str' => ['type' => Type::string()],
                'int' => ['type' => Type::int()],
            ],
            'interfaces' => [$fooType, $bazType],
        ]);

        $schema = new Schema(['types' => [$barType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Bar implements Foo & Baz {
              str: String
              int: Int
            }

            interface Foo {
              str: String
            }

            interface Baz {
              int: Int
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Hierarchical Interface')
     */
    public function testPrintHierarchicalInterface(): void
    {
        $FooType = new InterfaceType([
            'name' => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $BazType = new InterfaceType([
            'name' => 'Baz',
            'interfaces' => [$FooType],
            'fields' => [
                'int' => ['type' => Type::int()],
                'str' => ['type' => Type::string()],
            ],
        ]);

        $BarType = new ObjectType([
            'name' => 'Bar',
            'fields' => [
                'str' => ['type' => Type::string()],
                'int' => ['type' => Type::int()],
            ],
            'interfaces' => [$FooType, $BazType],
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => ['bar' => ['type' => $BarType]],
        ]);

        $schema = new Schema([
            'query' => $query,
            'types' => [$BarType],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Bar implements Foo & Baz {
              str: String
              int: Int
            }

            interface Foo {
              str: String
            }

            interface Baz implements Foo {
              int: Int
              str: String
            }

            type Query {
              bar: Bar
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Unions')
     */
    public function testPrintUnions(): void
    {
        $fooType = new ObjectType([
            'name' => 'Foo',
            'fields' => [
                'bool' => ['type' => Type::boolean()],
            ],
        ]);

        $barType = new ObjectType([
            'name' => 'Bar',
            'fields' => [
                'str' => ['type' => Type::string()],
            ],
        ]);

        $singleUnion = new UnionType([
            'name' => 'SingleUnion',
            'types' => [$fooType],
        ]);

        $multipleUnion = new UnionType([
            'name' => 'MultipleUnion',
            'types' => [$fooType, $barType],
        ]);

        $schema = new Schema([
            'types' => [$singleUnion, $multipleUnion],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
      union SingleUnion = Foo

      type Foo {
        bool: Boolean
      }

      union MultipleUnion = Foo | Bar

      type Bar {
        str: String
      }

      GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Input Type')
     */
    public function testInputType(): void
    {
        $inputType = new InputObjectType([
            'name' => 'InputType',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);

        $schema = new Schema(['types' => [$inputType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            input InputType {
              int: Int
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Custom Scalar')
     */
    public function testCustomScalar(): void
    {
        $oddType = new CustomScalarType([
            'name' => 'Odd',
            'serialize' => static fn () => null,
        ]);

        $schema = new Schema(['types' => [$oddType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            scalar Odd

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Enum')
     */
    public function testEnum(): void
    {
        $RGBType = new EnumType([
            'name' => 'RGB',
            'values' => [
                'RED' => [],
                'GREEN' => [],
                'BLUE' => [],
            ],
        ]);

        $schema = new Schema(['types' => [$RGBType]]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            enum RGB {
              RED
              GREEN
              BLUE
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints empty types')
     */
    public function testPrintsEmptyTypes(): void
    {
        $schema = new Schema([
            'types' => [
                new EnumType(['name' => 'SomeEnum', 'values' => []]),
                new InputObjectType(['name' => 'SomeInputObject', 'fields' => []]),
                new InterfaceType(['name' => 'SomeInterface', 'fields' => []]),
                new ObjectType(['name' => 'SomeObject', 'fields' => []]),
                new UnionType(['name' => 'SomeUnion', 'types' => []]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            enum SomeEnum

            input SomeInputObject

            interface SomeInterface

            type SomeObject

            union SomeUnion

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints custom directives')
     */
    public function testPrintsCustomDirectives(): void
    {
        $simpleDirective = new Directive([
            'name' => 'simpleDirective',
            'locations' => [DirectiveLocation::FIELD],
        ]);

        $complexDirective = new Directive([
            'name' => 'complexDirective',
            'description' => 'Complex Directive',
            'args' => [
                'stringArg' => ['type' => Type::string()],
                'intArg' => ['type' => Type::int(), 'defaultValue' => -1],
            ],
            'isRepeatable' => true,
            'locations' => [
                DirectiveLocation::FIELD,
                DirectiveLocation::QUERY,
            ],
        ]);

        $schema = new Schema([
            'directives' => [$simpleDirective, $complexDirective],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            directive @simpleDirective on FIELD

            "Complex Directive"
            directive @complexDirective(stringArg: String, intArg: Int = -1) repeatable on FIELD | QUERY

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints an empty description')
     */
    public function testPrintsAnEmptyDescription(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'description' => '',
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              ""
              singleField: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Prints a description with only whitespace', () => {
     */
    public function testPrintsADescriptionWithOnlyWhitespace(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'description' => ' ',
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              " "
              singleField: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('One-line prints a short description')
     */
    public function testOneLinePrintsAShortDescription(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'description' => 'This field is awesome',
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              "This field is awesome"
              singleField: String
            }

            GRAPHQL,
            $schema
        );
    }

    public function testEscapesOneLineDescription(): void
    {
        $schema = $this->buildSingleFieldSchema([
            'type' => Type::string(),
            'description' => 'foo\bar',
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Query {
              "foo\\bar"
              singleField: String
            }

            GRAPHQL,
            $schema
        );
    }

    /**
     * @see it('Print Introspection Schema')
     */
    public function testPrintIntrospectionSchema(): void
    {
        $schema = new Schema([]);
        $output = SchemaPrinter::printIntrospectionSchema($schema);

        $expected = <<<'GRAPHQL'
      "Directs the executor to include this field or fragment only when the `if` argument is true."
      directive @include(
        "Included when true."
        if: Boolean!
      ) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

      "Directs the executor to skip this field or fragment when the `if` argument is true."
      directive @skip(
        "Skipped when true."
        if: Boolean!
      ) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

      "Marks an element of a GraphQL schema as no longer supported."
      directive @deprecated(
        "Explains why this element was deprecated, usually also including a suggestion for how to access supported similar data. Formatted using the Markdown syntax, as specified by [CommonMark](https:\/\/commonmark.org\/)."
        reason: String = "No longer supported"
      ) on FIELD_DEFINITION | ENUM_VALUE

      "A GraphQL Schema defines the capabilities of a GraphQL server. It exposes all available types and directives on the server, as well as the entry points for query, mutation, and subscription operations."
      type __Schema {
        "A list of all types supported by this server."
        types: [__Type!]!

        "The type that query operations will be rooted at."
        queryType: __Type!

        "If this server supports mutation, the type that mutation operations will be rooted at."
        mutationType: __Type

        "If this server support subscription, the type that subscription operations will be rooted at."
        subscriptionType: __Type

        "A list of all directives supported by this server."
        directives: [__Directive!]!
      }

      """
      The fundamental unit of any GraphQL Schema is the type. There are many kinds of types in GraphQL as represented by the `__TypeKind` enum.

      Depending on the kind of a type, certain fields describe information about that type. Scalar types provide no information beyond a name and description, while Enum types provide their values. Object and Interface types provide the fields they describe. Abstract types, Union and Interface, provide the Object types possible at runtime. List and NonNull types compose other types.
      """
      type __Type {
        kind: __TypeKind!
        name: String
        description: String
        fields(includeDeprecated: Boolean = false): [__Field!]
        interfaces: [__Type!]
        possibleTypes: [__Type!]
        enumValues(includeDeprecated: Boolean = false): [__EnumValue!]
        inputFields: [__InputValue!]
        ofType: __Type
      }

      "An enum describing what kind of type a given `__Type` is."
      enum __TypeKind {
        "Indicates this type is a scalar."
        SCALAR

        "Indicates this type is an object. `fields` and `interfaces` are valid fields."
        OBJECT

        "Indicates this type is an interface. `fields`, `interfaces`, and `possibleTypes` are valid fields."
        INTERFACE

        "Indicates this type is a union. `possibleTypes` is a valid field."
        UNION

        "Indicates this type is an enum. `enumValues` is a valid field."
        ENUM

        "Indicates this type is an input object. `inputFields` is a valid field."
        INPUT_OBJECT

        "Indicates this type is a list. `ofType` is a valid field."
        LIST

        "Indicates this type is a non-null. `ofType` is a valid field."
        NON_NULL
      }

      "Object and Interface types are described by a list of Fields, each of which has a name, potentially a list of arguments, and a return type."
      type __Field {
        name: String!
        description: String
        args: [__InputValue!]!
        type: __Type!
        isDeprecated: Boolean!
        deprecationReason: String
      }

      "Arguments provided to Fields or Directives and the input fields of an InputObject are represented as Input Values which describe their type and optionally a default value."
      type __InputValue {
        name: String!
        description: String
        type: __Type!

        "A GraphQL-formatted string representing the default value for this input value."
        defaultValue: String
      }

      "One possible value for a given Enum. Enum values are unique values, not a placeholder for a string or numeric value. However an Enum value is returned in a JSON response as a string."
      type __EnumValue {
        name: String!
        description: String
        isDeprecated: Boolean!
        deprecationReason: String
      }

      """
      A Directive provides a way to describe alternate runtime execution and type validation behavior in a GraphQL document.

      In some cases, you need to provide options to alter GraphQL's execution behavior in ways field arguments will not suffice, such as conditionally including or skipping a field. Directives provide this by describing additional information to the executor.
      """
      type __Directive {
        name: String!
        description: String
        isRepeatable: Boolean!
        locations: [__DirectiveLocation!]!
        args: [__InputValue!]!
      }

      "A Directive can be adjacent to many parts of the GraphQL language, a __DirectiveLocation describes one such possible adjacencies."
      enum __DirectiveLocation {
        "Location adjacent to a query operation."
        QUERY

        "Location adjacent to a mutation operation."
        MUTATION

        "Location adjacent to a subscription operation."
        SUBSCRIPTION

        "Location adjacent to a field."
        FIELD

        "Location adjacent to a fragment definition."
        FRAGMENT_DEFINITION

        "Location adjacent to a fragment spread."
        FRAGMENT_SPREAD

        "Location adjacent to an inline fragment."
        INLINE_FRAGMENT

        "Location adjacent to a variable definition."
        VARIABLE_DEFINITION

        "Location adjacent to a schema definition."
        SCHEMA

        "Location adjacent to a scalar definition."
        SCALAR

        "Location adjacent to an object type definition."
        OBJECT

        "Location adjacent to a field definition."
        FIELD_DEFINITION

        "Location adjacent to an argument definition."
        ARGUMENT_DEFINITION

        "Location adjacent to an interface definition."
        INTERFACE

        "Location adjacent to a union definition."
        UNION

        "Location adjacent to an enum definition."
        ENUM

        "Location adjacent to an enum value definition."
        ENUM_VALUE

        "Location adjacent to an input object type definition."
        INPUT_OBJECT

        "Location adjacent to an input object field definition."
        INPUT_FIELD_DEFINITION
      }

      GRAPHQL;
        self::assertSame($expected, $output);
    }

    /**
     * Additional functionality not present in the reference implementation.
     */
    public function testPrintSchemaWithSortedTypes(): void
    {
        $schema = new Schema([
            'types' => [
                new EnumType(['name' => 'SomeEnum', 'values' => []]),
                new InputObjectType(['name' => 'InputObject', 'fields' => []]),
                new InterfaceType(['name' => 'FooInterface', 'fields' => []]),
                new ObjectType(['name' => 'Abc', 'fields' => []]),
                new UnionType(['name' => 'Union', 'types' => []]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            type Abc

            interface FooInterface

            input InputObject
            
            enum SomeEnum

            union Union

            GRAPHQL,
            $schema,
            ['sortTypes' => true]
        );
    }

    /**
     * Additional functionality not present in the reference implementation.
     */
    public function testPrintSchemaWithSortedFields(): void
    {
        $schema = new Schema([
            'types' => [
                new InputObjectType(['name' => 'InputObject', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
                new InterfaceType(['name' => 'FooInterface', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
                new ObjectType(['name' => 'Abc', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            input InputObject {
              foo: String
              bar: String
            }
            
            interface FooInterface {
              bar: String
              foo: String
            }
            
            type Abc {
              bar: String
              foo: String
            }

            GRAPHQL,
            $schema,
            ['sortFields' => true]
        );
    }

    /**
     * Additional functionality not present in the reference implementation.
     */
    public function testPrintSchemaWithSortedInputFields(): void
    {
        $schema = new Schema([
            'types' => [
                new InputObjectType(['name' => 'InputObject', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
                new InterfaceType(['name' => 'FooInterface', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
                new ObjectType(['name' => 'Abc', 'fields' => [
                    'foo' => ['type' => Type::string()],
                    'bar' => ['type' => Type::string()],
                ]]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            input InputObject {
              bar: String
              foo: String
            }
            
            interface FooInterface {
              foo: String
              bar: String
            }
            
            type Abc {
              foo: String
              bar: String
            }

            GRAPHQL,
            $schema,
            ['sortInputFields' => true]
        );
    }

    /**
     * Additional functionality not present in the reference implementation.
     */
    public function testPrintSchemaWithSortedEnumValues(): void
    {
        $schema = new Schema([
            'types' => [
                new EnumType([
                    'name' => 'RGB',
                    'values' => [
                        'RED' => [],
                        'GREEN' => [],
                        'BLUE' => [],
                    ],
                ]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            enum RGB {
              BLUE
              GREEN
              RED
            }

            GRAPHQL,
            $schema,
            ['sortEnumValues' => true]
        );
    }

    /**
     * Additional functionality not present in the reference implementation.
     */
    public function testPrintSchemaWithSortedArguments(): void
    {
        $schema = new Schema([
            'types' => [
                new InterfaceType(['name' => 'FooInterface', 'fields' => [
                    'myField' => [
                        'type' => Type::string(),
                        'args' => [
                            'foo' => ['type' => Type::int()],
                            'bar' => ['type' => Type::int()],
                        ],
                    ],
                ]]),
                new ObjectType(['name' => 'Abc', 'fields' => [
                    'myField' => [
                        'type' => Type::string(),
                        'args' => [
                            'foo' => ['type' => Type::int()],
                            'bar' => ['type' => Type::int()],
                        ],
                    ],
                ]]),
            ],
        ]);

        self::assertPrintedSchemaEquals(
            <<<'GRAPHQL'
            interface FooInterface {
              myField(bar: Int, foo: Int): String
            }
            
            type Abc {
              myField(bar: Int, foo: Int): String
            }

            GRAPHQL,
            $schema,
            ['sortArguments' => true]
        );
    }
}
