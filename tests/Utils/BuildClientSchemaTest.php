<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildClientSchema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use PHPUnit\Framework\TestCase;

/**
 * @see BuildClientSchema
 */
final class BuildClientSchemaTest extends TestCase
{
    /**
     * @throws \Exception
     * @throws InvariantViolation
     */
    protected static function assertCycleIntrospection(string $sdl): void
    {
        $options = ['directiveIsRepeatable' => true];

        $serverSchema = BuildSchema::build($sdl);
        $initialIntrospection = Introspection::fromSchema($serverSchema, $options);

        $clientSchema = BuildClientSchema::build($initialIntrospection);
        $secondIntrospection = Introspection::fromSchema($clientSchema, $options);

        self::assertSame($initialIntrospection, $secondIntrospection);
    }

    /**
     * @throws \Exception
     *
     * @return array<string, array<mixed>>
     */
    protected static function introspectionFromSDL(string $sdl): array
    {
        $schema = BuildSchema::build($sdl);

        return Introspection::fromSchema($schema);
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws InvariantViolation
     */
    protected static function clientSchemaFromSDL(string $sdl): Schema
    {
        $introspection = self::introspectionFromSDL($sdl);

        return BuildClientSchema::build($introspection);
    }

    // describe('Type System: build schema from introspection', () => {

    /** @see it('builds a simple schema', () => { */
    public function testBuildsASimpleSchema(): void
    {
        self::assertCycleIntrospection('
        schema {
          query: Simple
        }
        
        """This is a simple type"""
        type Simple {
          """This is a string field"""
          string: String
        }
        ');
    }

    /** it('builds a schema without the query type', () => {. */
    public function testBuildsASchemaWithoutTheQueryType(): void
    {
        $sdl = <<<SDL
type Query {
  foo: String
}

SDL;
        $introspection = self::introspectionFromSDL($sdl);
        unset($introspection['__schema']['queryType']);

        $clientSchema = BuildClientSchema::build($introspection);
        self::assertNull($clientSchema->getQueryType());
        self::assertSame($sdl, SchemaPrinter::doPrint($clientSchema));
    }

    /** it('builds a simple schema with all operation types', () => {. */
    public function testBuildsASimpleSchemaWithAllOperationTypes(): void
    {
        self::assertCycleIntrospection('
          schema {
            query: QueryType
            mutation: MutationType
            subscription: SubscriptionType
          }
    
          """This is a simple mutation type"""
          type MutationType {
            """Set the string field"""
            string: String
          }
    
          """This is a simple query type"""
          type QueryType {
            """This is a string field"""
            string: String
          }
    
          """This is a simple subscription type"""
          type SubscriptionType {
            """This is a string field"""
            string: String
          }
        ');
    }

    /** it('uses built-in scalars when possible', () => {. */
    public function testUsesBuiltInScalarsWhenPossible(): void
    {
        $sdl = '
          scalar CustomScalar
    
          type Query {
            int: Int
            float: Float
            string: String
            boolean: Boolean
            id: ID
            custom: CustomScalar
          }
        ';

        self::assertCycleIntrospection($sdl);

        $schema = BuildSchema::build($sdl);
        $introspection = Introspection::fromSchema($schema);
        $clientSchema = BuildClientSchema::build($introspection);

        // Built-ins are used
        self::assertSame(Type::int(), $clientSchema->getType('Int'));
        self::assertSame(Type::float(), $clientSchema->getType('Float'));
        self::assertSame(Type::string(), $clientSchema->getType('String'));
        self::assertSame(Type::boolean(), $clientSchema->getType('Boolean'));
        self::assertSame(Type::id(), $clientSchema->getType('ID'));

        // Custom are built
        self::assertNotSame(
            $schema->getType('CustomScalar'),
            $clientSchema->getType('CustomScalar')
        );
    }

    /** it('includes standard types only if they are used', () => {. */
    public function testIncludesStandardTypesOnlyIfTheyAreUsed(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
          type Query {
            foo: String
          }
        ');

        self::assertArrayNotHasKey('Int', $clientSchema->getTypeMap());

        self::markTestIncomplete('TODO we differ from graphql-js due to lazy loading, see https://github.com/webonyx/graphql-php/issues/964#issuecomment-945969162');
        self::assertNull($clientSchema->getType('Int'));
    }

    /** it('builds a schema with a recursive type reference', () => {. */
    public function testBuildsASchemaWithARecursiveTypeReference(): void
    {
        self::assertCycleIntrospection('
          schema {
            query: Recur
          }
    
          type Recur {
            recur: Recur
          }
        ');
    }

    /** it('builds a schema with a circular type reference', () => {. */
    public function testBuildsASchemaWithACircularTypeReference(): void
    {
        self::assertCycleIntrospection('
          type Dog {
            bestFriend: Human
          }
    
          type Human {
            bestFriend: Dog
          }
    
          type Query {
            dog: Dog
            human: Human
          }
        ');
    }

    /** it('builds a schema with an interface', () => {. */
    public function testBuildsASchemaWithAnInterface(): void
    {
        self::assertCycleIntrospection('
          type Dog implements Friendly {
            bestFriend: Friendly
          }
    
          interface Friendly {
            """The best friend of this friendly thing"""
            bestFriend: Friendly
          }
    
          type Human implements Friendly {
            bestFriend: Friendly
          }
    
          type Query {
            friendly: Friendly
          }
        ');
    }

    /** it('builds a schema with an interface hierarchy', () => {. */
    public function testBuildsASchemaWithAnInterfaceHierarchy(): void
    {
        self::assertCycleIntrospection('
          type Dog implements Friendly & Named {
            bestFriend: Friendly
            name: String
          }
    
          interface Friendly implements Named {
            """The best friend of this friendly thing"""
            bestFriend: Friendly
            name: String
          }
    
          type Human implements Friendly & Named {
            bestFriend: Friendly
            name: String
          }
    
          interface Named {
            name: String
          }
    
          type Query {
            friendly: Friendly
          }
        ');
    }

    /** it('builds a schema with an implicit interface', () => {. */
    public function testBuildsASchemaWithAnImplicitInterface(): void
    {
        self::assertCycleIntrospection('
          type Dog implements Friendly {
            bestFriend: Friendly
          }
    
          interface Friendly {
            """The best friend of this friendly thing"""
            bestFriend: Friendly
          }
    
          type Query {
            dog: Dog
          }
        ');
    }

    /** it('builds a schema with a union', () => {. */
    public function testBuildsASchemaWithAUnion(): void
    {
        self::assertCycleIntrospection('
          type Dog {
            bestFriend: Friendly
          }
    
          union Friendly = Dog | Human
    
          type Human {
            bestFriend: Friendly
          }
    
          type Query {
            friendly: Friendly
          }
        ');
    }

    /** it('builds a schema with complex field values', () => {. */
    public function testBuildsASchemaWithComplexFieldValues(): void
    {
        self::assertCycleIntrospection('
          type Query {
            string: String
            listOfString: [String]
            nonNullString: String!
            nonNullListOfString: [String]!
            nonNullListOfNonNullString: [String!]!
          }
        ');
    }

    /** it('builds a schema with field arguments', () => {. */
    public function testBuildsASchemaWithFieldArguments(): void
    {
        self::assertCycleIntrospection('
          type Query {
            """A field with a single arg"""
            one(
              """This is an int arg"""
              intArg: Int
            ): String
    
            """A field with a two args"""
            two(
              """This is an list of int arg"""
              listArg: [Int]
    
              """This is a required arg"""
              requiredArg: Boolean!
            ): String
          }
        ');
    }

    /** it('builds a schema with default value on custom scalar field', () => {. */
    public function testBuildsASchemaWithDefaultValueOnCustomScalarField(): void
    {
        self::assertCycleIntrospection('
          scalar CustomScalar
    
          type Query {
            testField(testArg: CustomScalar = "default"): String
          }
        ');
    }

    /** it('builds a schema with an enum', () => {. */
    public function testBuildsASchemaWithAnEnum(): void
    {
        $foodEnum = new EnumType([
            'name' => 'Food',
            'description' => 'Varieties of food stuffs',
            'values' => [
                'VEGETABLES' => [
                    'description' => 'Foods that are vegetables',
                    'value' => 1,
                ],
                'FRUITS' => ['value' => 2],
                'OILS' => [
                    'value' => 3,
                    'deprecationReason' => 'Too fatty',
                ],
            ],
        ]);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'EnumFields',
                'fields' => [
                    'food' => [
                        'description' => 'Repeats the arg you give it',
                        'type' => $foodEnum,
                        'args' => [
                            'kind' => [
                                'description' => 'what kind of food?',
                                'type' => $foodEnum,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $introspection = Introspection::fromSchema($schema);

        $clientSchema = BuildClientSchema::build($introspection);
        $introspectionFromClientSchema = Introspection::fromSchema($clientSchema);

        self::assertSame($introspection, $introspectionFromClientSchema);

        $clientFoodEnum = $clientSchema->getType('Food');
        self::assertInstanceOf(EnumType::class, $clientFoodEnum);

        self::assertCount(3, $clientFoodEnum->getValues());

        $vegetables = $clientFoodEnum->getValue('VEGETABLES');
        self::assertInstanceOf(EnumValueDefinition::class, $vegetables);

        // Client types do not get server-only values, so `value` mirrors `name`,
        // rather than using the integers defined in the "server" schema.
        self::assertSame('VEGETABLES', $vegetables->value);
        self::assertSame('Foods that are vegetables', $vegetables->description);
        self::assertFalse($vegetables->isDeprecated());
        self::assertNull($vegetables->deprecationReason);
        self::assertNull($vegetables->astNode);

        $fruits = $clientFoodEnum->getValue('FRUITS');
        self::assertInstanceOf(EnumValueDefinition::class, $fruits);
        self::assertNull($fruits->description);

        $oils = $clientFoodEnum->getValue('OILS');
        self::assertInstanceOf(EnumValueDefinition::class, $oils);
        self::assertTrue($oils->isDeprecated());
        self::assertSame('Too fatty', $oils->deprecationReason);
    }

    /** it('builds a schema with an input object', () => {. */
    public function testBuildsASchemaWithAnInputObject(): void
    {
        self::assertCycleIntrospection('
          """An input address"""
          input Address {
            """What street is this address?"""
            street: String!
    
            """The city the address is within?"""
            city: String!
    
            """The country (blank will assume USA)."""
            country: String = "USA"
          }
    
          type Query {
            """Get a geocode from an address"""
            geocode(
              """The address to lookup"""
              address: Address
            ): String
          }
        ');
    }

    /** it('builds a schema with field arguments with default values', () => {. */
    public function testBuildsASchemaWithFieldArgumentsWithDefaultValues(): void
    {
        self::assertCycleIntrospection('
          input Geo {
            lat: Float
            lon: Float
          }
    
          type Query {
            defaultInt(intArg: Int = 30): String
            defaultList(listArg: [Int] = [1, 2, 3]): String
            defaultObject(objArg: Geo = {lat: 37.485, lon: -122.148}): String
            defaultNull(intArg: Int = null): String
            noDefault(intArg: Int): String
          }
        ');
    }

    /** it('builds a schema with custom directives', () => {. */
    public function testBuildsASchemaWithCustomDirectives(): void
    {
        self::assertCycleIntrospection('
          """This is a custom directive"""
          directive @customDirective repeatable on FIELD

          type Query {
            string: String
          }
        ');
    }

    /** it('builds a schema without directives', () => {. */
    public function testBuildsASchemaWithoutDirectives(): void
    {
        $sdl = <<<SDL
type Query {
  string: String
}

SDL;

        $schema = BuildSchema::build($sdl);
        $introspection = Introspection::fromSchema($schema);

        unset($introspection['__schema']['directives']);

        $clientSchema = BuildClientSchema::build($introspection);

        self::assertNotEmpty($schema->getDirectives());
        self::assertSame([], $clientSchema->getDirectives());
        self::assertSame($sdl, SchemaPrinter::doPrint($clientSchema));
    }

    /** it('builds a schema aware of deprecation', () => {. */
    public function testBuildsASchemaAwareOfDeprecation(): void
    {
        self::assertCycleIntrospection('
          enum Color {
            """So rosy"""
            RED
    
            """So grassy"""
            GREEN
    
            """So calming"""
            BLUE
    
            """So sickening"""
            MAUVE @deprecated(reason: "No longer in fashion")
          }
    
          type Query {
            """This is a shiny string field"""
            shinyString: String
    
            """This is a deprecated string field"""
            deprecatedString: String @deprecated(reason: "Use shinyString")
            color: Color
          }
        ');
    }

    /** it('builds a schema with empty deprecation reasons', () => {. */
    public function testBuildsASchemaWithEmptyDeprecationReasons(): void
    {
        self::assertCycleIntrospection('
          type Query {
            someField: String @deprecated(reason: "")
          }
    
          enum SomeEnum {
            SOME_VALUE @deprecated(reason: "")
          }
        ');
    }

    /** it('can use client schema for limited execution', () => {. */
    public function testUseClientSchemaForLimitedExecution(): void
    {
        $schema = BuildSchema::build('
          scalar CustomScalar
    
          type Query {
            foo(custom1: CustomScalar, custom2: CustomScalar): String
          }
        ');

        $introspection = Introspection::fromSchema($schema);
        $clientSchema = BuildClientSchema::build($introspection);

        $result = GraphQL::executeQuery(
            $clientSchema,
            'query Limited($v: CustomScalar) { foo(custom1: 123, custom2: $v) }',
            ['foo' => 'bar', 'unused' => 'value'],
            null,
            ['v' => 'baz']
        );

        self::assertSame(['foo' => 'bar'], $result->data);
    }

    // describe('throws when given invalid introspection', () => {

    /**
     * Construct a default dummy schema that is used in the following tests.
     *
     * @throws \Exception
     */
    protected static function dummySchema(): Schema
    {
        return BuildSchema::build('
          type Query {
            foo(bar: String): String
          }
    
          interface SomeInterface {
            foo: String
          }
    
          union SomeUnion = Query
    
          enum SomeEnum { FOO }
    
          input SomeInputObject {
            foo: String
          }
    
          directive @SomeDirective on QUERY
        ');
    }

    /** it('throws when introspection is missing __schema property', () => {. */
    public function testThrowsWhenIntrospectionIsMissingSchemaProperty(): void
    {
        $this->expectExceptionMessage(
            'Invalid or incomplete introspection result. Ensure that you are passing "data" property of introspection response and no "errors" was returned alongside: [].'
        );
        BuildClientSchema::build([]);
    }

    /** it('throws when referenced unknown type', () => {. */
    public function testThrowsWhenReferencedUnknownType(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());

        $introspection['__schema']['types'] = array_filter(
            $introspection['__schema']['types'],
            static fn (array $type): bool => $type['name'] !== 'Query'
        );

        $this->expectExceptionMessage(
            'Invalid or incomplete schema, unknown type: Query. Ensure that a full introspection query is used in order to build a client schema.'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing definition for one of the standard scalars', () => {. */
    public function testThrowsWhenMissingDefinitionForOneOfTheStandardScalars(): void
    {
        $schema = BuildSchema::build('
        type Query {
          foo: Float
        }
        ');
        $introspection = Introspection::fromSchema($schema);

        $introspection['__schema']['types'] = array_filter(
            $introspection['__schema']['types'],
            static fn (array $type): bool => $type['name'] !== 'Float'
        );

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessage('Invalid or incomplete schema, unknown type: Float. Ensure that a full introspection query is used in order to build a client schema.');
        $clientSchema->assertValid();
    }

    /** it('throws when type reference is missing name', () => {. */
    public function testThrowsWhenTypeReferenceIsMissingName(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());

        self::assertNotEmpty($introspection['__schema']['queryType']['name']);

        unset($introspection['__schema']['queryType']['name']);

        $this->expectExceptionMessage('Unknown type reference: [].');
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing kind', () => {. */
    public function testThrowsWhenMissingKind(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('kind', $queryTypeIntrospection);

        unset($queryTypeIntrospection['kind']);

        $this->expectExceptionMessageMatches(
            '/Invalid or incomplete introspection result. Ensure that a full introspection query is used in order to build a client schema: {"name":"Query",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    public function testThrowsOnUnknownKind(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('kind', $queryTypeIntrospection);

        $queryTypeIntrospection['kind'] = 3;

        $this->expectExceptionMessageMatches(
            '/Invalid or incomplete introspection result. Received type with unknown kind: {"kind":3,"name":"Query",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    public function testThrowsWhenMissingName(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('name', $queryTypeIntrospection);

        unset($queryTypeIntrospection['name']);

        $this->expectExceptionMessageMatches(
            '/Invalid or incomplete introspection result. Ensure that a full introspection query is used in order to build a client schema: {"kind":"OBJECT",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing interfaces', () => {. */
    public function testThrowsWhenMissingInterfaces(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('interfaces', $queryTypeIntrospection);
        unset($queryTypeIntrospection['interfaces']);

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessageMatches('/Introspection result missing interfaces: {"kind":"OBJECT","name":"Query",.*}\./');
        $clientSchema->assertValid();
    }

    /** it('Legacy support for interfaces with null as interfaces field', () => {. */
    public function testLegacySupportForInterfacesWithNullAsInterfacesField(): void
    {
        $dummySchema = self::dummySchema();
        $introspection = Introspection::fromSchema($dummySchema);
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'SomeInterface') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('interfaces', $queryTypeIntrospection);

        $queryTypeIntrospection['interfaces'] = null;

        $clientSchema = BuildClientSchema::build($introspection);
        self::assertSame(
            SchemaPrinter::doPrint($dummySchema),
            SchemaPrinter::doPrint($clientSchema)
        );
    }

    /** it('throws when missing fields', () => {. */
    public function testThrowsWhenMissingFields(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('fields', $queryTypeIntrospection);
        unset($queryTypeIntrospection['fields']);

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessageMatches('/Introspection result missing fields: {"kind":"OBJECT","name":"Query",.*}\./');
        $clientSchema->assertValid();
    }

    /** it('throws when missing field args', () => {. */
    public function testThrowsWhenMissingFieldArgs(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        $firstField = &$queryTypeIntrospection['fields'][0];
        self::assertArrayHasKey('args', $firstField);
        unset($firstField['args']);

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessageMatches('/Introspection result missing field args: {"name":"foo",.*}\./');
        $clientSchema->assertValid();
    }

    /** it('throws when output type is used as an arg type', () => {. */
    public function testThrowsWhenOutputTypeIsUsedAsAnArgType(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        $firstArgType = &$queryTypeIntrospection['fields'][0]['args'][0]['type'];
        self::assertArrayHasKey('name', $firstArgType);
        $firstArgType['name'] = 'SomeUnion';

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessage('Introspection must provide input type for arguments, but received: SomeUnion.');
        $clientSchema->assertValid();
    }

    /** it('throws when input type is used as a field type', () => {. */
    public function testThrowsWhenInputTypeIsUsedAsAFieldType(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $queryTypeIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'Query') {
                $queryTypeIntrospection = &$type;
            }
        }

        $firstFieldType = &$queryTypeIntrospection['fields'][0]['type'];
        self::assertArrayHasKey('name', $firstFieldType);
        $firstFieldType['name'] = 'SomeInputObject';

        $clientSchema = BuildClientSchema::build($introspection);

        $this->expectExceptionMessage('Introspection must provide output type for fields, but received: SomeInputObject.');
        $clientSchema->assertValid();
    }

    /** it('throws when missing possibleTypes', () => {. */
    public function testThrowsWhenMissingPossibleTypes(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $someUnionIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'SomeUnion') {
                $someUnionIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('possibleTypes', $someUnionIntrospection);

        unset($someUnionIntrospection['possibleTypes']);

        $this->expectExceptionMessageMatches(
            '/Introspection result missing possibleTypes: {"kind":"UNION","name":"SomeUnion",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing enumValues', () => {. */
    public function testThrowsWhenMissingEnumValues(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $someEnumIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'SomeEnum') {
                $someEnumIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('enumValues', $someEnumIntrospection);

        unset($someEnumIntrospection['enumValues']);

        $this->expectExceptionMessageMatches(
            '/Introspection result missing enumValues: {"kind":"ENUM","name":"SomeEnum",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing inputFields', () => {. */
    public function testThrowsWhenMissingInputFields(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());
        $someInputObjectIntrospection = null;
        foreach ($introspection['__schema']['types'] as &$type) {
            if ($type['name'] === 'SomeInputObject') {
                $someInputObjectIntrospection = &$type;
            }
        }

        self::assertArrayHasKey('inputFields', $someInputObjectIntrospection);

        unset($someInputObjectIntrospection['inputFields']);

        $this->expectExceptionMessageMatches(
            '/Introspection result missing inputFields: {"kind":"INPUT_OBJECT","name":"SomeInputObject",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing directive locations', () => {. */
    public function testThrowsWhenMissingDirectiveLocations(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());

        $someDirectiveIntrospection = &$introspection['__schema']['directives'][0];
        self::assertSame('SomeDirective', $someDirectiveIntrospection['name']);
        self::assertSame(['QUERY'], $someDirectiveIntrospection['locations']);

        unset($someDirectiveIntrospection['locations']);

        $this->expectExceptionMessageMatches(
            '/Introspection result missing directive locations: {"name":"SomeDirective",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    /** it('throws when missing directive args', () => {. */
    public function testThrowsWhenMissingDirectiveArgs(): void
    {
        $introspection = Introspection::fromSchema(self::dummySchema());

        $someDirectiveIntrospection = &$introspection['__schema']['directives'][0];
        self::assertSame('SomeDirective', $someDirectiveIntrospection['name']);
        self::assertSame([], $someDirectiveIntrospection['args']);

        unset($someDirectiveIntrospection['args']);

        $this->expectExceptionMessageMatches(
            '/Introspection result missing directive args: {"name":"SomeDirective",.*}\./'
        );
        BuildClientSchema::build($introspection);
    }

    // describe('very deep decorators are not supported', () => {

    /** it('fails on very deep (> 7 levels) lists', () => {. */
    public function testFailsOnVeryDeepListsWithMoreThan7Levels(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
        type Query {
          foo: [[[[[[[[String]]]]]]]]
        }
        ');

        $this->expectExceptionMessage('Decorated type deeper than introspection query.');
        $clientSchema->assertValid();
    }

    /** it('fails on very deep (> 7 levels) non-null', () => {. */
    public function testFailsOnVeryDeepNonNullWithMoreThan7Levels(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
        type Query {
          foo: [[[[String!]!]!]!]
        }
        ');

        $this->expectExceptionMessage('Decorated type deeper than introspection query.');
        $clientSchema->assertValid();
    }

    /** it('succeeds on deep (<= 7 levels) types', () => {. */
    public function testSucceedsOnDeepTypesWithMoreThanOrEqualTo7Levels(): void
    {
        // e.g., fully non-null 3D matrix
        self::assertCycleIntrospection('
        type Query {
          foo: [[[String!]!]!]!
        }
        ');
    }

    // describe('prevents infinite recursion on invalid introspection', () => {

    /** it('recursive interfaces', () => {. */
    public function testRecursiveInterfaces(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
        type Query {
          foo: Foo
        }

        type Foo implements Foo {
          foo: String
        }
        ');

        $this->expectExceptionMessage('Expected Foo to be a GraphQL Interface type.');
        $clientSchema->assertValid();
    }

    /** it('recursive union', () => {. */
    public function testRecursiveUnion(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
        type Query {
          foo: Foo
        }

        union Foo = Foo
        ');

        $this->expectExceptionMessage('Expected Foo to be a GraphQL Object type.');
        $clientSchema->assertValid();
    }

    public function testCustomScalarValueMixedExecution(): void
    {
        $schema = BuildSchema::build('
        scalar CustomScalar
    
        type Query {
          foo(bar: CustomScalar): CustomScalar
        }
        ');

        $introspection = Introspection::fromSchema($schema);
        $clientSchema = BuildClientSchema::build($introspection);

        $result = GraphQL::executeQuery(
            $clientSchema,
            'query CustomScalarObject($v: CustomScalar) { foo(bar: $v) }',
            ['foo' => ['baz' => 'value']],
            null,
            ['v' => 100]
        );

        self::assertSame(['foo' => ['baz' => 'value']], $result->data);
    }
}
