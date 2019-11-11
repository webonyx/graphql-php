<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\EnumType;
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
class BuildClientSchemaTest extends TestCase
{
    protected static function assertCycleIntrospection(string $sdl) : void
    {
        $serverSchema         = BuildSchema::build($sdl);
        $initialIntrospection = Introspection::fromSchema($serverSchema);
        $clientSchema         = BuildClientSchema::build($initialIntrospection);
        $secondIntrospection  = Introspection::fromSchema($clientSchema);

        self::assertSame($initialIntrospection, $secondIntrospection);
    }

    /**
     * @return array<string, mixed[]>
     */
    protected static function introspectionFromSDL(string $sdl) : array
    {
        $schema = BuildSchema::build($sdl);

        return Introspection::fromSchema($schema);
    }

    protected static function clientSchemaFromSDL(string $sdl) : Schema
    {
        $introspection = self::introspectionFromSDL($sdl);

        return BuildClientSchema::build($introspection);
    }

    // describe('Type System: build schema from introspection', () => {

    /**
     * @see it('builds a simple schema', () => {
     */
    public function testBuildsASimpleSchema() : void
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

    /**
     * it('builds a schema without the query type', () => {
     */
    public function testBuildsASchemaWithoutTheQueryType() : void
    {
        $sdl           = '
        type Query {
          foo: String
        }
        ';
        $introspection = self::introspectionFromSDL($sdl);
        unset($introspection['__schema']['queryType']);

        $clientSchema = BuildClientSchema::build($introspection);
        self::assertNull($clientSchema->getQueryType());
        self::markTestSkipped('Why should this assertion be true?');
        self::assertSame($sdl, SchemaPrinter::printIntrospectionSchema($clientSchema));
    }

    /**
     * it('builds a simple schema with all operation types', () => {
     */
    public function testBuildsASimpleSchemaWithAllOperationTypes() : void
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

    /**
     * it('uses built-in scalars when possible', () => {
     */
    public function testUsesBuiltInScalarsWhenPossible() : void
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

        $schema        = BuildSchema::build($sdl);
        $introspection = Introspection::fromSchema($schema);
        $clientSchema  = BuildClientSchema::build($introspection);

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

    /**
     * it('includes standard types only if they are used', () => {
     */
    public function testIncludesStandardTypesOnlyIfTheyAreUsed() : void
    {
        $this->markTestSkipped('Introspection currently does not follow the reference implementation.');
        $clientSchema = self::clientSchemaFromSDL('
          type Query {
            foo: String
          }
        ');

        self::assertNull($clientSchema->getType('Int'));
    }

    /**
     * it('builds a schema with a recursive type reference', () => {
     */
    public function testBuildsASchemaWithARecursiveTypeReference() : void
    {
        $this->assertCycleIntrospection('
          schema {
            query: Recur
          }
    
          type Recur {
            recur: Recur
          }
        ');
    }

    /**
     * it('builds a schema with a circular type reference', () => {
     */
    public function testBuildsASchemaWithACircularTypeReference() : void
    {
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with an interface', () => {
     */
    public function testBuildsASchemaWithAnInterface() : void
    {
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with an interface hierarchy', () => {
     */
    public function testBuildsASchemaWithAnInterfaceHierarchy() : void
    {
        self::markTestSkipped('Will work only once intermediate interfaces are possible');
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with an implicit interface', () => {
     */
    public function testBuildsASchemaWithAnImplicitInterface() : void
    {
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with a union', () => {
     */
    public function testBuildsASchemaWithAUnion() : void
    {
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with complex field values', () => {
     */
    public function testBuildsASchemaWithComplexFieldValues() : void
    {
        $this->assertCycleIntrospection('
          type Query {
            string: String
            listOfString: [String]
            nonNullString: String!
            nonNullListOfString: [String]!
            nonNullListOfNonNullString: [String!]!
          }
        ');
    }

    /**
     * it('builds a schema with field arguments', () => {
     */
    public function testBuildsASchemaWithFieldArguments() : void
    {
        $this->assertCycleIntrospection('
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

    /**
     * it('builds a schema with default value on custom scalar field', () => {
     */
    public function testBuildsASchemaWithDefaultValueOnCustomScalarField() : void
    {
        $this->assertCycleIntrospection('
          scalar CustomScalar
    
          type Query {
            testField(testArg: CustomScalar = "default"): String
          }
        ');
    }

    /**
     * it('builds a schema with an enum', () => {
     */
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
                'FRUITS' => [
                    'value' => 2,
                ],
                'OILS' => [
                    'value' => 3,
                    'deprecationReason' => 'Too fatty'
                ]
            ]
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
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $introspection = Introspection::fromSchema($schema);
        $clientSchema = BuildClientSchema::build($introspection);

        $introspectionFromClientSchema = Introspection::fromSchema($clientSchema);
        self::assertSame($introspection, $introspectionFromClientSchema);

        /** @var EnumType $clientFoodEnum */
        $clientFoodEnum = $clientSchema->getType('Food');
        self::assertInstanceOf(EnumType::class, $clientFoodEnum);

        self::assertCount(3, $clientFoodEnum->getValues());

        $vegetables = $clientFoodEnum->getValue('VEGETABLES');

        // Client types do not get server-only values, so `value` mirrors `name`,
        // rather than using the integers defined in the "server" schema.
        self::assertSame('VEGETABLES', $vegetables->value);
        self::assertSame('Foods that are vegetables', $vegetables->description);
        self::assertFalse($vegetables->isDeprecated());
        self::assertNull($vegetables->deprecationReason);
        self::assertNull($vegetables->astNode);

        $fruits = $clientFoodEnum->getValue('FRUITS');
        self::assertNull($fruits->description);

        $oils = $clientFoodEnum->getValue('OILS');
        self::assertTrue($oils->isDeprecated());
        self::assertSame('Too fatty', $oils->deprecationReason);
    }

    /**
     * it('builds a schema with an input object', () => {
     */
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

    /**
     * it('builds a schema with field arguments with default values', () => {
     */
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

    /**
     * it('builds a schema with custom directives', () => {
     */
    public function testBuildsASchemaWithCustomDirectives(): void
    {
        self::assertCycleIntrospection('
          """This is a custom directive"""
          directive @customDirective on FIELD
    
          type Query {
            string: String
          }
        ');
    }

    /**
     * it('builds a schema without directives', () => {
     */
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

    /**
     * it('builds a schema aware of deprecation', () => {
     */
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

    /**
     * it('builds a schema with empty deprecation reasons', () => {
     */
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

    /**
     * it('can use client schema for limited execution', () => {
     */
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
}
