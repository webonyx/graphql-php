<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

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

class SchemaPrinterTest extends TestCase
{
    // Describe: Type System Printer

    /**
     * @see it('Prints String Field')
     */
    public function testPrintsStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: String
}
',
            $output
        );
    }

    private function printSingleFieldSchema($fieldConfig)
    {
        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => ['singleField' => $fieldConfig],
        ]);

        return $this->printForTest(new Schema(['query' => $query]));
    }

    private function printForTest($schema)
    {
        $schemaText = SchemaPrinter::doPrint($schema);
        self::assertEquals($schemaText, SchemaPrinter::doPrint(BuildSchema::build($schemaText)));

        return "\n" . $schemaText;
    }

    /**
     * @see it('Prints [String] Field')
     */
    public function testPrintArrayStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::listOf(Type::string()),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: [String]
}
',
            $output
        );
    }

    /**
     * @see it('Prints String! Field')
     */
    public function testPrintNonNullStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::nonNull(Type::string()),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: String!
}
',
            $output
        );
    }

    /**
     * @see it('Prints [String]! Field')
     */
    public function testPrintNonNullArrayStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::nonNull(Type::listOf(Type::string())),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: [String]!
}
',
            $output
        );
    }

    /**
     * @see it('Prints [String!] Field')
     */
    public function testPrintArrayNonNullStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::listOf(Type::nonNull(Type::string())),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: [String!]
}
',
            $output
        );
    }

    /**
     * @see it('Prints [String!]! Field')
     */
    public function testPrintNonNullArrayNonNullStringField() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
        ]);
        self::assertEquals(
            '
type Query {
  singleField: [String!]!
}
',
            $output
        );
    }

    /**
     * @see it('Print Object Field')
     */
    public function testPrintObjectField() : void
    {
        $fooType = new ObjectType([
            'name'   => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $root = new ObjectType([
            'name'   => 'Query',
            'fields' => ['foo' => ['type' => $fooType]],
        ]);

        $schema = new Schema(['query' => $root]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
type Foo {
  str: String
}

type Query {
  foo: Foo
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Int Arg')
     */
    public function testPrintsStringFieldWithIntArg() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int()]],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Int Arg With Default')
     */
    public function testPrintsStringFieldWithIntArgWithDefault() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int(), 'defaultValue' => 2]],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int = 2): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With String Arg With Default')
     */
    public function testPrintsStringFieldWithStringArgWithDefault() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::string(), 'defaultValue' => "tes\t de\fault"]],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: String = "tes\t de\fault"): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Int Arg With Default Null')
     */
    public function testPrintsStringFieldWithIntArgWithDefaultNull() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::int(), 'defaultValue' => null]],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int = null): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Int! Arg')
     */
    public function testPrintsStringFieldWithNonNullIntArg() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => ['argOne' => ['type' => Type::nonNull(Type::int())]],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int!): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args')
     */
    public function testPrintsStringFieldWithMultipleArgs() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne' => ['type' => Type::int()],
                'argTwo' => ['type' => Type::string()],
            ],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int, argTwo: String): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, First is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsFirstIsDefault() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne'   => ['type' => Type::int(), 'defaultValue' => 1],
                'argTwo'   => ['type' => Type::string()],
                'argThree' => ['type' => Type::boolean()],
            ],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int = 1, argTwo: String, argThree: Boolean): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, Second is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsSecondIsDefault() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne'   => ['type' => Type::int()],
                'argTwo'   => ['type' => Type::string(), 'defaultValue' => 'foo'],
                'argThree' => ['type' => Type::boolean()],
            ],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int, argTwo: String = "foo", argThree: Boolean): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints String Field With Multiple Args, Last is Default')
     */
    public function testPrintsStringFieldWithMultipleArgsLastIsDefault() : void
    {
        $output = $this->printSingleFieldSchema([
            'type' => Type::string(),
            'args' => [
                'argOne'   => ['type' => Type::int()],
                'argTwo'   => ['type' => Type::string()],
                'argThree' => ['type' => Type::boolean(), 'defaultValue' => false],
            ],
        ]);
        self::assertEquals(
            '
type Query {
  singleField(argOne: Int, argTwo: String, argThree: Boolean = false): String
}
',
            $output
        );
    }

    /**
     * @see it('Prints custom query root type')
     */
    public function testPrintsCustomQueryRootType() : void
    {
        $customQueryType = new ObjectType([
            'name'   => 'CustomQueryType',
            'fields' => ['bar' => ['type' => Type::string()]],
        ]);

        $schema   = new Schema(['query' => $customQueryType]);
        $output   = $this->printForTest($schema);
        $expected = '
schema {
  query: CustomQueryType
}

type CustomQueryType {
  bar: String
}
';
        self::assertEquals($expected, $output);
    }

    /**
     * @see it('Print Interface')
     */
    public function testPrintInterface() : void
    {
        $fooType = new InterfaceType([
            'name'   => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $barType = new ObjectType([
            'name'       => 'Bar',
            'fields'     => ['str' => ['type' => Type::string()]],
            'interfaces' => [$fooType],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => ['bar' => ['type' => $barType]],
        ]);

        $schema = new Schema([
            'query' => $query,
            'types' => [$barType],
        ]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
type Bar implements Foo {
  str: String
}

interface Foo {
  str: String
}

type Query {
  bar: Bar
}
',
            $output
        );
    }

    /**
     * @see it('Print Multiple Interface')
     */
    public function testPrintMultipleInterface() : void
    {
        $fooType = new InterfaceType([
            'name'   => 'Foo',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $baazType = new InterfaceType([
            'name'   => 'Baaz',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);

        $barType = new ObjectType([
            'name'       => 'Bar',
            'fields'     => [
                'str' => ['type' => Type::string()],
                'int' => ['type' => Type::int()],
            ],
            'interfaces' => [$fooType, $baazType],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => ['bar' => ['type' => $barType]],
        ]);

        $schema = new Schema([
            'query' => $query,
            'types' => [$barType],
        ]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
interface Baaz {
  int: Int
}

type Bar implements Foo & Baaz {
  str: String
  int: Int
}

interface Foo {
  str: String
}

type Query {
  bar: Bar
}
',
            $output
        );
    }

    /**
     * @see it('Print Unions')
     */
    public function testPrintUnions() : void
    {
        $fooType = new ObjectType([
            'name'   => 'Foo',
            'fields' => ['bool' => ['type' => Type::boolean()]],
        ]);

        $barType = new ObjectType([
            'name'   => 'Bar',
            'fields' => ['str' => ['type' => Type::string()]],
        ]);

        $singleUnion = new UnionType([
            'name'  => 'SingleUnion',
            'types' => [$fooType],
        ]);

        $multipleUnion = new UnionType([
            'name'  => 'MultipleUnion',
            'types' => [$fooType, $barType],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'single'   => ['type' => $singleUnion],
                'multiple' => ['type' => $multipleUnion],
            ],
        ]);

        $schema = new Schema(['query' => $query]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
type Bar {
  str: String
}

type Foo {
  bool: Boolean
}

union MultipleUnion = Foo | Bar

type Query {
  single: SingleUnion
  multiple: MultipleUnion
}

union SingleUnion = Foo
',
            $output
        );
    }

    /**
     * @see it('Print Input Type')
     */
    public function testInputType() : void
    {
        $inputType = new InputObjectType([
            'name'   => 'InputType',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'str' => [
                    'type' => Type::string(),
                    'args' => ['argOne' => ['type' => $inputType]],
                ],
            ],
        ]);

        $schema = new Schema(['query' => $query]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
input InputType {
  int: Int
}

type Query {
  str(argOne: InputType): String
}
',
            $output
        );
    }

    /**
     * @see it('Custom Scalar')
     */
    public function testCustomScalar() : void
    {
        $oddType = new CustomScalarType([
            'name'      => 'Odd',
            'serialize' => static function ($value) {
                return $value % 2 === 1 ? $value : null;
            },
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'odd' => ['type' => $oddType],
            ],
        ]);

        $schema = new Schema(['query' => $query]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
scalar Odd

type Query {
  odd: Odd
}
',
            $output
        );
    }

    /**
     * @see it('Enum')
     */
    public function testEnum() : void
    {
        $RGBType = new EnumType([
            'name'   => 'RGB',
            'values' => [
                'RED'   => ['value' => 0],
                'GREEN' => ['value' => 1],
                'BLUE'  => ['value' => 2],
            ],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'rgb' => ['type' => $RGBType],
            ],
        ]);

        $schema = new Schema(['query' => $query]);
        $output = $this->printForTest($schema);
        self::assertEquals(
            '
type Query {
  rgb: RGB
}

enum RGB {
  RED
  GREEN
  BLUE
}
',
            $output
        );
    }

    /**
     * @see it('Prints custom directives')
     */
    public function testPrintsCustomDirectives() : void
    {
        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'field' => ['type' => Type::string()],
            ],
        ]);

        $customDirectives = new Directive([
            'name'      => 'customDirective',
            'locations' => [
                DirectiveLocation::FIELD,
            ],
        ]);

        $schema = new Schema([
            'query'      => $query,
            'directives' => [$customDirectives],
        ]);

        $output = $this->printForTest($schema);
        self::assertEquals(
            '
directive @customDirective on FIELD

type Query {
  field: String
}
',
            $output
        );
    }

    /**
     * @see it('One-line prints a short description')
     */
    public function testOneLinePrintsAShortDescription() : void
    {
        $description = 'This field is awesome';
        $output      = $this->printSingleFieldSchema([
            'type'        => Type::string(),
            'description' => $description,
        ]);

        self::assertEquals(
            '
type Query {
  """This field is awesome"""
  singleField: String
}
',
            $output
        );

        /** @var ObjectType $recreatedRoot */
        $recreatedRoot  = BuildSchema::build($output)->getTypeMap()['Query'];
        $recreatedField = $recreatedRoot->getFields()['singleField'];
        self::assertEquals($description, $recreatedField->description);
    }

    /**
     * @see it('Does not one-line print a description that ends with a quote')
     */
    public function testDoesNotOneLinePrintADescriptionThatEndsWithAQuote() : void
    {
        $description = 'This field is "awesome"';
        $output      = $this->printSingleFieldSchema([
            'type'        => Type::string(),
            'description' => $description,
        ]);

        self::assertEquals(
            '
type Query {
  """
  This field is "awesome"
  """
  singleField: String
}
',
            $output
        );

        /** @var ObjectType $recreatedRoot */
        $recreatedRoot  = BuildSchema::build($output)->getTypeMap()['Query'];
        $recreatedField = $recreatedRoot->getFields()['singleField'];
        self::assertEquals($description, $recreatedField->description);
    }

    /**
     * @see it('Preserves leading spaces when printing a description')
     */
    public function testPReservesLeadingSpacesWhenPrintingADescription() : void
    {
        $description = '    This field is "awesome"';
        $output      = $this->printSingleFieldSchema([
            'type'        => Type::string(),
            'description' => $description,
        ]);

        self::assertEquals(
            '
type Query {
  """    This field is "awesome"
  """
  singleField: String
}
',
            $output
        );

        /** @var ObjectType $recreatedRoot */
        $recreatedRoot  = BuildSchema::build($output)->getTypeMap()['Query'];
        $recreatedField = $recreatedRoot->getFields()['singleField'];
        self::assertEquals($description, $recreatedField->description);
    }

    /**
     * @see it('Print Introspection Schema')
     */
    public function testPrintIntrospectionSchema() : void
    {
        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'onlyField' => ['type' => Type::string()],
            ],
        ]);

        $schema              = new Schema(['query' => $query]);
        $output              = SchemaPrinter::printIntrospectionSchema($schema);
        $introspectionSchema = <<<'EOT'
"""
Directs the executor to include this field or fragment only when the `if` argument is true.
"""
directive @include(
  """Included when true."""
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

"""
Directs the executor to skip this field or fragment when the `if` argument is true.
"""
directive @skip(
  """Skipped when true."""
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

"""Marks an element of a GraphQL schema as no longer supported."""
directive @deprecated(
  """
  Explains why this element was deprecated, usually also including a suggestion
  for how to access supported similar data. Formatted in
  [Markdown](https://daringfireball.net/projects/markdown/).
  """
  reason: String = "No longer supported"
) on FIELD_DEFINITION | ENUM_VALUE

"""
A Directive provides a way to describe alternate runtime execution and type validation behavior in a GraphQL document.

In some cases, you need to provide options to alter GraphQL's execution behavior
in ways field arguments will not suffice, such as conditionally including or
skipping a field. Directives provide this by describing additional information
to the executor.
"""
type __Directive {
  name: String!
  description: String
  locations: [__DirectiveLocation!]!
  args: [__InputValue!]!
}

"""
A Directive can be adjacent to many parts of the GraphQL language, a
__DirectiveLocation describes one such possible adjacencies.
"""
enum __DirectiveLocation {
  """Location adjacent to a query operation."""
  QUERY

  """Location adjacent to a mutation operation."""
  MUTATION

  """Location adjacent to a subscription operation."""
  SUBSCRIPTION

  """Location adjacent to a field."""
  FIELD

  """Location adjacent to a fragment definition."""
  FRAGMENT_DEFINITION

  """Location adjacent to a fragment spread."""
  FRAGMENT_SPREAD

  """Location adjacent to an inline fragment."""
  INLINE_FRAGMENT

  """Location adjacent to a variable definition."""
  VARIABLE_DEFINITION

  """Location adjacent to a schema definition."""
  SCHEMA

  """Location adjacent to a scalar definition."""
  SCALAR

  """Location adjacent to an object type definition."""
  OBJECT

  """Location adjacent to a field definition."""
  FIELD_DEFINITION

  """Location adjacent to an argument definition."""
  ARGUMENT_DEFINITION

  """Location adjacent to an interface definition."""
  INTERFACE

  """Location adjacent to a union definition."""
  UNION

  """Location adjacent to an enum definition."""
  ENUM

  """Location adjacent to an enum value definition."""
  ENUM_VALUE

  """Location adjacent to an input object type definition."""
  INPUT_OBJECT

  """Location adjacent to an input object field definition."""
  INPUT_FIELD_DEFINITION
}

"""
One possible value for a given Enum. Enum values are unique values, not a
placeholder for a string or numeric value. However an Enum value is returned in
a JSON response as a string.
"""
type __EnumValue {
  name: String!
  description: String
  isDeprecated: Boolean!
  deprecationReason: String
}

"""
Object and Interface types are described by a list of Fields, each of which has
a name, potentially a list of arguments, and a return type.
"""
type __Field {
  name: String!
  description: String
  args: [__InputValue!]!
  type: __Type!
  isDeprecated: Boolean!
  deprecationReason: String
}

"""
Arguments provided to Fields or Directives and the input fields of an
InputObject are represented as Input Values which describe their type and
optionally a default value.
"""
type __InputValue {
  name: String!
  description: String
  type: __Type!

  """
  A GraphQL-formatted string representing the default value for this input value.
  """
  defaultValue: String
}

"""
A GraphQL Schema defines the capabilities of a GraphQL server. It exposes all
available types and directives on the server, as well as the entry points for
query, mutation, and subscription operations.
"""
type __Schema {
  """A list of all types supported by this server."""
  types: [__Type!]!

  """The type that query operations will be rooted at."""
  queryType: __Type!

  """
  If this server supports mutation, the type that mutation operations will be rooted at.
  """
  mutationType: __Type

  """
  If this server support subscription, the type that subscription operations will be rooted at.
  """
  subscriptionType: __Type

  """A list of all directives supported by this server."""
  directives: [__Directive!]!
}

"""
The fundamental unit of any GraphQL Schema is the type. There are many kinds of
types in GraphQL as represented by the `__TypeKind` enum.

Depending on the kind of a type, certain fields describe information about that
type. Scalar types provide no information beyond a name and description, while
Enum types provide their values. Object and Interface types provide the fields
they describe. Abstract types, Union and Interface, provide the Object types
possible at runtime. List and NonNull types compose other types.
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

"""An enum describing what kind of type a given `__Type` is."""
enum __TypeKind {
  """Indicates this type is a scalar."""
  SCALAR

  """
  Indicates this type is an object. `fields` and `interfaces` are valid fields.
  """
  OBJECT

  """
  Indicates this type is an interface. `fields` and `possibleTypes` are valid fields.
  """
  INTERFACE

  """Indicates this type is a union. `possibleTypes` is a valid field."""
  UNION

  """Indicates this type is an enum. `enumValues` is a valid field."""
  ENUM

  """
  Indicates this type is an input object. `inputFields` is a valid field.
  """
  INPUT_OBJECT

  """Indicates this type is a list. `ofType` is a valid field."""
  LIST

  """Indicates this type is a non-null. `ofType` is a valid field."""
  NON_NULL
}

EOT;
        self::assertEquals($introspectionSchema, $output);
    }

    /**
     * @see it('Print Introspection Schema with comment descriptions')
     */
    public function testPrintIntrospectionSchemaWithCommentDescriptions() : void
    {
        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'onlyField' => ['type' => Type::string()],
            ],
        ]);

        $schema              = new Schema(['query' => $query]);
        $output              = SchemaPrinter::printIntrospectionSchema(
            $schema,
            ['commentDescriptions' => true]
        );
        $introspectionSchema = <<<'EOT'
# Directs the executor to include this field or fragment only when the `if` argument is true.
directive @include(
  # Included when true.
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

# Directs the executor to skip this field or fragment when the `if` argument is true.
directive @skip(
  # Skipped when true.
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

# Marks an element of a GraphQL schema as no longer supported.
directive @deprecated(
  # Explains why this element was deprecated, usually also including a suggestion
  # for how to access supported similar data. Formatted in
  # [Markdown](https://daringfireball.net/projects/markdown/).
  reason: String = "No longer supported"
) on FIELD_DEFINITION | ENUM_VALUE

# A Directive provides a way to describe alternate runtime execution and type validation behavior in a GraphQL document.
#
# In some cases, you need to provide options to alter GraphQL's execution behavior
# in ways field arguments will not suffice, such as conditionally including or
# skipping a field. Directives provide this by describing additional information
# to the executor.
type __Directive {
  name: String!
  description: String
  locations: [__DirectiveLocation!]!
  args: [__InputValue!]!
}

# A Directive can be adjacent to many parts of the GraphQL language, a
# __DirectiveLocation describes one such possible adjacencies.
enum __DirectiveLocation {
  # Location adjacent to a query operation.
  QUERY

  # Location adjacent to a mutation operation.
  MUTATION

  # Location adjacent to a subscription operation.
  SUBSCRIPTION

  # Location adjacent to a field.
  FIELD

  # Location adjacent to a fragment definition.
  FRAGMENT_DEFINITION

  # Location adjacent to a fragment spread.
  FRAGMENT_SPREAD

  # Location adjacent to an inline fragment.
  INLINE_FRAGMENT

  # Location adjacent to a variable definition.
  VARIABLE_DEFINITION

  # Location adjacent to a schema definition.
  SCHEMA

  # Location adjacent to a scalar definition.
  SCALAR

  # Location adjacent to an object type definition.
  OBJECT

  # Location adjacent to a field definition.
  FIELD_DEFINITION

  # Location adjacent to an argument definition.
  ARGUMENT_DEFINITION

  # Location adjacent to an interface definition.
  INTERFACE

  # Location adjacent to a union definition.
  UNION

  # Location adjacent to an enum definition.
  ENUM

  # Location adjacent to an enum value definition.
  ENUM_VALUE

  # Location adjacent to an input object type definition.
  INPUT_OBJECT

  # Location adjacent to an input object field definition.
  INPUT_FIELD_DEFINITION
}

# One possible value for a given Enum. Enum values are unique values, not a
# placeholder for a string or numeric value. However an Enum value is returned in
# a JSON response as a string.
type __EnumValue {
  name: String!
  description: String
  isDeprecated: Boolean!
  deprecationReason: String
}

# Object and Interface types are described by a list of Fields, each of which has
# a name, potentially a list of arguments, and a return type.
type __Field {
  name: String!
  description: String
  args: [__InputValue!]!
  type: __Type!
  isDeprecated: Boolean!
  deprecationReason: String
}

# Arguments provided to Fields or Directives and the input fields of an
# InputObject are represented as Input Values which describe their type and
# optionally a default value.
type __InputValue {
  name: String!
  description: String
  type: __Type!

  # A GraphQL-formatted string representing the default value for this input value.
  defaultValue: String
}

# A GraphQL Schema defines the capabilities of a GraphQL server. It exposes all
# available types and directives on the server, as well as the entry points for
# query, mutation, and subscription operations.
type __Schema {
  # A list of all types supported by this server.
  types: [__Type!]!

  # The type that query operations will be rooted at.
  queryType: __Type!

  # If this server supports mutation, the type that mutation operations will be rooted at.
  mutationType: __Type

  # If this server support subscription, the type that subscription operations will be rooted at.
  subscriptionType: __Type

  # A list of all directives supported by this server.
  directives: [__Directive!]!
}

# The fundamental unit of any GraphQL Schema is the type. There are many kinds of
# types in GraphQL as represented by the `__TypeKind` enum.
#
# Depending on the kind of a type, certain fields describe information about that
# type. Scalar types provide no information beyond a name and description, while
# Enum types provide their values. Object and Interface types provide the fields
# they describe. Abstract types, Union and Interface, provide the Object types
# possible at runtime. List and NonNull types compose other types.
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

# An enum describing what kind of type a given `__Type` is.
enum __TypeKind {
  # Indicates this type is a scalar.
  SCALAR

  # Indicates this type is an object. `fields` and `interfaces` are valid fields.
  OBJECT

  # Indicates this type is an interface. `fields` and `possibleTypes` are valid fields.
  INTERFACE

  # Indicates this type is a union. `possibleTypes` is a valid field.
  UNION

  # Indicates this type is an enum. `enumValues` is a valid field.
  ENUM

  # Indicates this type is an input object. `inputFields` is a valid field.
  INPUT_OBJECT

  # Indicates this type is a list. `ofType` is a valid field.
  LIST

  # Indicates this type is a non-null. `ofType` is a valid field.
  NON_NULL
}

EOT;
        self::assertEquals($introspectionSchema, $output);
    }

    public function testPrintSchemaDirectiveNoArgs() {
      $exceptedSdl = "
directive @sd on OBJECT

type Bar @sd {
  foo: String
}

type Query {
  foo: Bar
}
";

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveWithStringArgs() {
      $exceptedSdl = '
directive @sd(field: String!) on OBJECT

type Bar @sd(field: "String") {
  foo: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveWithNumberArgs() {
      $exceptedSdl = '
directive @sd(field: Int!) on OBJECT

type Bar @sd(field: 1) {
  foo: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveWithArrayArgs() {
      $exceptedSdl = '
directive @sd(field: [Int!]) on OBJECT

type Bar @sd(field: [1, 2, 3, 4]) {
  foo: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveWithTypeArgs() {
      $exceptedSdl = '
directive @sd(field: Foo) on OBJECT

type Bar @sd(field: {bar: "test"}) {
  foo: String
}

type Foo {
  bar: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveOptionalArgs() {
      $exceptedSdl = '
directive @sd(field: String) on OBJECT

type Bar @sd(field: "Testing") {
  foo: String
}

type Foo @sd {
  bar: String
}

type Query {
  foo: Bar
  bar: Foo
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintMultipleSchemaDirectives() {
      $exceptedSdl = '
directive @sd(field: [Int!]) on OBJECT

directive @sdb on OBJECT

type Bar @sd(field: [1, 2, 3, 4]) @sdb {
  foo: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveOnClassWithInterface() {
      $exceptedSdl = '
directive @sd on OBJECT

type Bar implements Foo @sd {
  foo: String
}

interface Foo {
  bar: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }

    public function testPrintSchemaDirectiveOnInterface() {
      $exceptedSdl = '
directive @sd on INTERFACE

type Bar implements Foo {
  foo: String
}

interface Foo @sd {
  bar: String
}

type Query {
  foo: Bar
}
';

      $schema = BuildSchema::build($exceptedSdl);
      $actual = $this->printForTest($schema);

      self::assertEquals($exceptedSdl, $actual);
    }
}
