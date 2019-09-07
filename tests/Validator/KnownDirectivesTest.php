<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\KnownDirectives;

class KnownDirectivesTest extends ValidatorTestCase
{
    /** @var Schema */
    public $schemaWithSDLDirectives;

    public function setUp()
    {
        $this->schemaWithSDLDirectives = BuildSchema::build('
          directive @onSchema on SCHEMA
          directive @onScalar on SCALAR
          directive @onObject on OBJECT
          directive @onFieldDefinition on FIELD_DEFINITION
          directive @onArgumentDefinition on ARGUMENT_DEFINITION
          directive @onInterface on INTERFACE
          directive @onUnion on UNION
          directive @onEnum on ENUM
          directive @onEnumValue on ENUM_VALUE
          directive @onInputObject on INPUT_OBJECT
          directive @onInputFieldDefinition on INPUT_FIELD_DEFINITION
        ');
    }

    private function expectSDLErrors($sdlString, $schema = null, $errors = [])
    {
        return $this->expectSDLErrorsFromRule(new KnownDirectives(), $sdlString, $schema, $errors);
    }

    // Validate: Known directives

    /**
     * @see it('with no directives')
     */
    public function testWithNoDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      query Foo {
        name
        ...Frag
      }

      fragment Frag on Dog {
        name
      }
        '
        );
    }

    /**
     * @see it('with known directives')
     */
    public function testWithKnownDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      {
        dog @include(if: true) {
          name
        }
        human @skip(if: true) {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('with unknown directive')
     */
    public function testWithUnknownDirective() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      {
        dog @unknown(directive: "value") {
          name
        }
      }
        ',
            [$this->unknownDirective('unknown', 3, 13)]
        );
    }

    private function unknownDirective($directiveName, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::unknownDirectiveMessage($directiveName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('with many unknown directives')
     */
    public function testWithManyUnknownDirectives() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      {
        dog @unknown(directive: "value") {
          name
        }
        human @unknown(directive: "value") {
          name
          pets @unknown(directive: "value") {
            name
          }
        }
      }
        ',
            [
                $this->unknownDirective('unknown', 3, 13),
                $this->unknownDirective('unknown', 6, 15),
                $this->unknownDirective('unknown', 8, 16),
            ]
        );
    }

    /**
     * @see it('with well placed directives')
     */
    public function testWithWellPlacedDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      query Foo @onQuery {
        name @include(if: true)
        ...Frag @include(if: true)
        skippedField @skip(if: true)
        ...SkippedFrag @skip(if: true)
      }
      
      mutation Bar @onMutation {
        someField
      }
        '
        );
    }

    // DESCRIBE: within SDL

    /**
     * @see it('with directive defined inside SDL')
     */
    public function testWithDirectiveDefinedInsideSDL()
    {
        $this->expectSDLErrors('
            type Query {
              foo: String @test
            }
    
            directive @test on FIELD_DEFINITION
        ', null, []);
    }

    /**
     * @see it('with standard directive')
     */
    public function testWithStandardDirective()
    {
        $this->expectSDLErrors(
            '
            type Query {
              foo: String @deprecated
            }',
            null,
            []
        );
    }

    /**
     * @see it('with overrided standard directive')
     */
    public function testWithOverridedStandardDirective()
    {
        $this->expectSDLErrors(
            '
            schema @deprecated {
              query: Query
            }
            directive @deprecated on SCHEMA',
            null,
            []
        );
    }

    /**
     * @see it('with directive defined in schema extension')
     */
    public function testWithDirectiveDefinedInSchemaExtension()
    {
        $schema = BuildSchema::build('
          type Query {
            foo: String
          }
        ');
        $this->expectSDLErrors(
            '
            directive @test on OBJECT
    
            extend type Query  @test
            ',
            $schema,
            []
        );
    }

    /**
     * @see it('with directive used in schema extension')
     */
    public function testWithDirectiveUsedInSchemaExtension()
    {
        $schema = BuildSchema::build('
            directive @test on OBJECT
    
            type Query {
              foo: String
            }
        ');
        $this->expectSDLErrors(
            '
            extend type Query @test
            ',
            $schema,
            []
        );
    }

    /**
     * @see it('with unknown directive in schema extension')
     */
    public function testWithUnknownDirectiveInSchemaExtension()
    {
        $schema = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $this->expectSDLErrors(
            '
          extend type Query @unknown
            ',
            $schema,
            [$this->unknownDirective('unknown', 2, 29)]
        );
    }

    /**
     * @see it('with misplaced directives')
     */
    public function testWithMisplacedDirectives() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      query Foo @include(if: true) {
        name @onQuery
        ...Frag @onQuery
      }

      mutation Bar @onQuery {
        someField
      }
        ',
            [
                $this->misplacedDirective('include', 'QUERY', 2, 17),
                $this->misplacedDirective('onQuery', 'FIELD', 3, 14),
                $this->misplacedDirective('onQuery', 'FRAGMENT_SPREAD', 4, 17),
                $this->misplacedDirective('onQuery', 'MUTATION', 7, 20),
            ]
        );
    }

    private function misplacedDirective($directiveName, $placement, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::misplacedDirectiveMessage($directiveName, $placement),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('with well placed directives')
     */
    public function testWSLWithWellPlacedDirectives() : void
    {
        $this->expectSDLErrors(
            '
        type MyObj implements MyInterface @onObject {
          myField(myArg: Int @onArgumentDefinition): String @onFieldDefinition
        }

        extend type MyObj @onObject

        scalar MyScalar @onScalar
        
        extend scalar MyScalar @onScalar

        interface MyInterface @onInterface {
          myField(myArg: Int @onArgumentDefinition): String @onFieldDefinition
        }
        
        extend interface MyInterface @onInterface

        union MyUnion @onUnion = MyObj | Other
        
        extend union MyUnion @onUnion

        enum MyEnum @onEnum {
          MY_VALUE @onEnumValue
        }
        
        extend enum MyEnum @onEnum

        input MyInput @onInputObject {
          myField: Int @onInputFieldDefinition
        }
        
        extend input MyInput @onInputObject

        schema @onSchema {
          query: MyQuery
        }
        ',
            $this->schemaWithSDLDirectives,
            []
        );
    }

    /**
     * @see it('with misplaced directives')
     */
    public function testWSLWithMisplacedDirectives() : void
    {
        $this->expectSDLErrors(
            '
        type MyObj implements MyInterface @onInterface {
          myField(myArg: Int @onInputFieldDefinition): String @onInputFieldDefinition
        }

        scalar MyScalar @onEnum

        interface MyInterface @onObject {
          myField(myArg: Int @onInputFieldDefinition): String @onInputFieldDefinition
        }

        union MyUnion @onEnumValue = MyObj | Other

        enum MyEnum @onScalar {
          MY_VALUE @onUnion
        }

        input MyInput @onEnum {
          myField: Int @onArgumentDefinition
        }

        schema @onObject {
          query: MyQuery
        }
        ',
            $this->schemaWithSDLDirectives,
            [
                $this->misplacedDirective('onInterface', 'OBJECT', 2, 43),
                $this->misplacedDirective('onInputFieldDefinition', 'ARGUMENT_DEFINITION', 3, 30),
                $this->misplacedDirective('onInputFieldDefinition', 'FIELD_DEFINITION', 3, 63),
                $this->misplacedDirective('onEnum', 'SCALAR', 6, 25),
                $this->misplacedDirective('onObject', 'INTERFACE', 8, 31),
                $this->misplacedDirective('onInputFieldDefinition', 'ARGUMENT_DEFINITION', 9, 30),
                $this->misplacedDirective('onInputFieldDefinition', 'FIELD_DEFINITION', 9, 63),
                $this->misplacedDirective('onEnumValue', 'UNION', 12, 23),
                $this->misplacedDirective('onScalar', 'ENUM', 14, 21),
                $this->misplacedDirective('onUnion', 'ENUM_VALUE', 15, 20),
                $this->misplacedDirective('onEnum', 'INPUT_OBJECT', 18, 23),
                $this->misplacedDirective('onArgumentDefinition', 'INPUT_FIELD_DEFINITION', 19, 24),
                $this->misplacedDirective('onObject', 'SCHEMA', 22, 16),
            ]
        );
    }
}
