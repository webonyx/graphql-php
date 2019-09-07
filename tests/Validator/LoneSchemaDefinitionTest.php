<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\KnownDirectives;
use GraphQL\Validator\Rules\LoneSchemaDefinition;

class LoneSchemaDefinitionTest extends ValidatorTestCase
{
    private function expectSDLErrors($sdlString, $schema = null, $errors = [])
    {
        return $this->expectSDLErrorsFromRule(new LoneSchemaDefinition(), $sdlString, $schema, $errors);
    }

    private function schemaDefinitionNotAlone($line, $column)
    {
        return FormattedError::create(
            LoneSchemaDefinition::schemaDefinitionNotAloneMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    private function canNotDefineSchemaWithinExtension($line, $column)
    {
        return FormattedError::create(
            LoneSchemaDefinition::canNotDefineSchemaWithinExtensionMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    // Validate: Schema definition should be alone

    /**
     * @see it('no schema')
     */
    public function testNoSchema()
    {
        $this->expectSDLErrors(
            '
          type Query {
            foo: String
          }
            ',
            null,
            []
        );
    }

    /**
     * @see it('one schema definition')
     */
    public function testOneSchemaDefinition()
    {
        $this->expectSDLErrors(
            '
          schema {
            query: Foo
          }
    
          type Foo {
            foo: String
          }
            ',
            null,
            []
        );
    }

    /**
     * @see it('multiple schema definitions')
     */
    public function testMultipleSchemaDefinitions()
    {
        $this->expectSDLErrors(
            '
      schema {
        query: Foo
      }

      type Foo {
        foo: String
      }

      schema {
        mutation: Foo
      }

      schema {
        subscription: Foo
      }
            ',
            null,
            [
                $this->schemaDefinitionNotAlone(10, 7),
                $this->schemaDefinitionNotAlone(14, 7),
            ]
        );
    }

    /**
     * @see it('define schema in schema extension')
     */
    public function testDefineSchemaInSchemaExtension()
    {
        $schema = BuildSchema::build('
          type Foo {
            foo: String
          }
        ');

        $this->expectSDLErrors(
            '
                schema {
                  query: Foo
                }
            ',
            $schema,
            []
        );
    }

    /**
     * @see it('redefine schema in schema extension')
     */
    public function testRedefineSchemaInSchemaExtension()
    {
        $schema = BuildSchema::build('
          schema {
            query: Foo
          }
    
          type Foo {
            foo: String
          }');

        $this->expectSDLErrors(
            '
                schema {
                  mutation: Foo
                }
            ',
            $schema,
            [$this->canNotDefineSchemaWithinExtension(2, 17)]
        );
    }

    /**
     * @see it('redefine implicit schema in schema extension')
     */
    public function testRedefineImplicitSchemaInSchemaExtension()
    {
        $schema = BuildSchema::build('
          type Query {
            fooField: Foo
          }
    
          type Foo {
            foo: String
          }
        ');

        $this->expectSDLErrors(
            '
                schema {
                  mutation: Foo
                }
            ',
            $schema,
            [$this->canNotDefineSchemaWithinExtension(2, 17)]
        );
    }

    /**
     * @see it('extend schema in schema extension')
     */
    public function testExtendSchemaInSchemaExtension()
    {
        $schema = BuildSchema::build('
          type Query {
            fooField: Foo
          }
    
          type Foo {
            foo: String
          }
        ');

        $this->expectSDLErrors(
            '
                extend schema {
                  mutation: Foo
                }
            ',
            $schema,
            []
        );
    }
}
