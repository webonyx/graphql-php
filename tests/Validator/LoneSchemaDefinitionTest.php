<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\LoneSchemaDefinition;

class LoneSchemaDefinitionTest extends ValidatorTestCase
{
    private function expectSDLErrors($sdlString, $schema = null, $errors = []): void
    {
        $this->expectSDLErrorsFromRule(new LoneSchemaDefinition(), $sdlString, $schema, $errors);
    }

    private function schemaDefinitionNotAlone($line, $column)
    {
        return ErrorHelper::create(
            LoneSchemaDefinition::schemaDefinitionNotAloneMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    private function canNotDefineSchemaWithinExtension($line, $column)
    {
        return ErrorHelper::create(
            LoneSchemaDefinition::canNotDefineSchemaWithinExtensionMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    // Validate: Schema definition should be alone

    /**
     * @see it('no schema')
     */
    public function testNoSchema(): void
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
    public function testOneSchemaDefinition(): void
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
    public function testMultipleSchemaDefinitions(): void
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
    public function testDefineSchemaInSchemaExtension(): void
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
    public function testRedefineSchemaInSchemaExtension(): void
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
    public function testRedefineImplicitSchemaInSchemaExtension(): void
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
    public function testExtendSchemaInSchemaExtension(): void
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
