<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * Contains tests originating from `graphql-js` that previously were in BuildSchemaTest.
 * Their counterparts have been removed from `buildASTSchema-test.js` and moved elsewhere,
 * but these changes to `graphql-js` haven't been reflected in `graphql-php` yet.
 */
class BuildSchemaLegacyTest extends TestCase
{
    // Describe: Failures

    /**
     * @see it('Allows only a single query type')
     */
    public function testAllowsOnlySingleQueryType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one query type in schema.');
        $sdl = '
            schema {
              query: Hello
              query: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single mutation type')
     */
    public function testAllowsOnlySingleMutationType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one mutation type in schema.');
        $sdl = '
            schema {
              query: Hello
              mutation: Hello
              mutation: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single subscription type')
     */
    public function testAllowsOnlySingleSubscriptionType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one subscription type in schema.');
        $sdl = '
            schema {
              query: Hello
              subscription: Hello
              subscription: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown type referenced')
     */
    public function testUnknownTypeReferenced(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $sdl    = '
            schema {
              query: Hello
            }
            
            type Hello {
              bar: Bar
            }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in interface list')
     */
    public function testUnknownTypeInInterfaceList(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $sdl    = '
            type Query implements Bar {
              field: String
            }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in union list')
     */
    public function testUnknownTypeInUnionList(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $sdl    = '
            union TestUnion = Bar
            type Query { testUnion: TestUnion }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown query type')
     */
    public function testUnknownQueryType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Wat" not found in document.');
        $sdl = '
            schema {
              query: Wat
            }
            
            type Hello {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown mutation type')
     */
    public function testUnknownMutationType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified mutation type "Wat" not found in document.');
        $sdl = '
            schema {
              query: Hello
              mutation: Wat
            }
            
            type Hello {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown subscription type')
     */
    public function testUnknownSubscriptionType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified subscription type "Awesome" not found in document.');
        $sdl = '
            schema {
              query: Hello
              mutation: Wat
              subscription: Awesome
            }
            
            type Hello {
              str: String
            }
            
            type Wat {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider directive names')
     */
    public function testDoesNotConsiderDirectiveNames(): void
    {
        $sdl = '
          schema {
            query: Foo
          }
    
          directive @Foo on QUERY
        ';
        $doc = Parser::parse($sdl);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        BuildSchema::build($doc);
    }

    /**
     * @see it('Does not consider operation names')
     */
    public function testDoesNotConsiderOperationNames(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $sdl = '
            schema {
              query: Foo
            }
            
            query Foo { field }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider fragment names')
     */
    public function testDoesNotConsiderFragmentNames(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $sdl = '
            schema {
              query: Foo
            }
            
            fragment Foo on Type { field }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Forbids duplicate type definitions')
     */
    public function testForbidsDuplicateTypeDefinitions(): void
    {
        $sdl = '
            schema {
              query: Repeated
            }
            
            type Repeated {
              id: Int
            }
            
            type Repeated {
              id: String
            }
        ';
        $doc = Parser::parse($sdl);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Repeated" was defined more than once.');
        BuildSchema::buildAST($doc);
    }
}
