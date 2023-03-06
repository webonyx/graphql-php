<?php declare(strict_types=1);

namespace GraphQL\Tests\Error;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    /**
     * @see it('uses the stack of an original error')
     */
    public function testUsesTheStackOfAnOriginalError(): void
    {
        $prev = new \Exception('Original');
        $err = new Error('msg', null, null, [], null, $prev);

        self::assertSame($err->getPrevious(), $prev);
    }

    /**
     * @see it('converts nodes to positions and locations')
     */
    public function testConvertsNodesToPositionsAndLocations(): void
    {
        $source = new Source('{
      field
    }');
        $ast = Parser::parse($source);
        /** @var OperationDefinitionNode $operationDefinition */
        $operationDefinition = $ast->definitions[0];
        $fieldNode = $operationDefinition->selectionSet->selections[0];
        $e = new Error('msg', [$fieldNode]);

        self::assertEquals([$fieldNode], $e->getNodes());
        self::assertEquals($source, $e->getSource());
        self::assertSame([8], $e->getPositions());
        self::assertEquals([new SourceLocation(2, 7)], $e->getLocations());
    }

    /**
     * @see it('converts single node to positions and locations')
     */
    public function testConvertSingleNodeToPositionsAndLocations(): void
    {
        $source = new Source('{
      field
    }');
        $ast = Parser::parse($source);
        /** @var OperationDefinitionNode $operationDefinition */
        $operationDefinition = $ast->definitions[0];
        $fieldNode = $operationDefinition->selectionSet->selections[0];
        $e = new Error('msg', $fieldNode); // Non-array value.

        self::assertEquals([$fieldNode], $e->getNodes());
        self::assertEquals($source, $e->getSource());
        self::assertSame([8], $e->getPositions());
        self::assertEquals([new SourceLocation(2, 7)], $e->getLocations());
    }

    /**
     * @see it('converts node with loc.start === 0 to positions and locations')
     */
    public function testConvertsNodeWithStart0ToPositionsAndLocations(): void
    {
        $source = new Source('{
      field
    }');
        $ast = Parser::parse($source);
        $operationNode = $ast->definitions[0];
        $e = new Error('msg', [$operationNode]);

        self::assertEquals([$operationNode], $e->getNodes());
        self::assertEquals($source, $e->getSource());
        self::assertSame([0], $e->getPositions());
        self::assertEquals([new SourceLocation(1, 1)], $e->getLocations());
    }

    /**
     * @see it('converts source and positions to locations')
     */
    public function testConvertsSourceAndPositionsToLocations(): void
    {
        $source = new Source('{
      field
    }');
        $e = new Error('msg', null, $source, [10]);

        self::assertEquals(null, $e->getNodes());
        self::assertEquals($source, $e->getSource());
        self::assertSame([10], $e->getPositions());
        self::assertEquals([new SourceLocation(2, 9)], $e->getLocations());
    }

    /**
     * @see it('serializes to include message')
     */
    public function testSerializesToIncludeMessage(): void
    {
        $e = new Error('msg');
        self::assertSame(['message' => 'msg'], FormattedError::createFromException($e));
    }

    /**
     * @see it('serializes to include message and locations')
     */
    public function testSerializesToIncludeMessageAndLocations(): void
    {
        $ast = Parser::parse('{ field }');
        /** @var OperationDefinitionNode $operationDefinition */
        $operationDefinition = $ast->definitions[0];
        $node = $operationDefinition->selectionSet->selections[0];
        $e = new Error('msg', [$node]);

        self::assertSame(
            ['message' => 'msg', 'locations' => [['line' => 1, 'column' => 3]]],
            FormattedError::createFromException($e)
        );
    }

    /**
     * @see it('serializes to include path')
     */
    public function testSerializesToIncludePath(): void
    {
        $e = new Error(
            'msg',
            null,
            null,
            [],
            ['path', 3, 'to', 'field']
        );

        self::assertSame(['path', 3, 'to', 'field'], $e->path);
        self::assertSame(['message' => 'msg', 'path' => ['path', 3, 'to', 'field']], FormattedError::createFromException($e));
    }

    /**
     * @see it('default error formatter includes extension fields')
     */
    public function testDefaultErrorFormatterIncludesExtensionFields(): void
    {
        $e = new Error(
            'msg',
            null,
            null,
            [],
            null,
            null,
            ['foo' => 'bar']
        );

        self::assertSame(['foo' => 'bar'], $e->getExtensions());
        self::assertSame(
            [
                'message' => 'msg',
                'extensions' => ['foo' => 'bar'],
            ],
            FormattedError::createFromException($e)
        );
    }

    public function testErrorReadsOverridenMethods(): void
    {
        $error = new class('msg', null, null, [], null, null, ['foo' => 'bar']) extends Error {
            public function getExtensions(): ?array
            {
                $extensions = parent::getExtensions();
                $extensions['subfoo'] = 'subbar';

                return $extensions;
            }

            public function getPositions(): array
            {
                return [1 => 2];
            }

            public function getSource(): ?Source
            {
                return new Source('');
            }

            public function getNodes(): ?array
            {
                return [];
            }
        };

        $locatedError = Error::createLocatedError($error);

        self::assertSame(['foo' => 'bar', 'subfoo' => 'subbar'], $locatedError->getExtensions());
        self::assertSame([], $locatedError->getNodes());
        self::assertSame([1 => 2], $locatedError->getPositions());
        self::assertNotNull($locatedError->getSource());

        $error = new class('msg', new NullValueNode([]), null, []) extends Error {
            public function getNodes(): ?array
            {
                return [new NullValueNode([])];
            }

            public function getPath(): ?array
            {
                return ['path'];
            }
        };
        self::assertSame($error, Error::createLocatedError($error));
    }
}
