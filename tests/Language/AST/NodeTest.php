<?php declare(strict_types=1);

namespace GraphQL\Tests\Language\AST;

use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

use function Safe\json_encode;

final class NodeTest extends TestCase
{
    public function testCloneDeep(): void
    {
        $node = Parser::objectTypeDefinition('
        type Test {
            id(arg: Int): ID!
        }
        ');
        $clone = $node->cloneDeep();

        self::assertNotSameButEquals($node, $clone);
        self::assertNotSameButEquals($node->name, $clone->name);
        self::assertNotSameButEquals($node->directives, $clone->directives);

        $nodeFields = $node->fields;
        $cloneFields = $clone->fields;
        self::assertNotSameButEquals($nodeFields, $cloneFields);

        $nodeId = $nodeFields[0];
        $cloneId = $cloneFields[0];
        self::assertNotSameButEquals($nodeId, $cloneId);

        $nodeIdArgs = $nodeId->arguments;
        $cloneIdArgs = $cloneId->arguments;
        self::assertNotSameButEquals($nodeIdArgs, $cloneIdArgs);

        $nodeArg = $nodeIdArgs[0];
        $cloneArg = $cloneIdArgs[0];
        self::assertNotSameButEquals($nodeArg, $cloneArg);

        self::assertSame($node->loc, $clone->loc);
    }

    private static function assertNotSameButEquals(object $node, object $clone): void
    {
        self::assertNotSame($node, $clone);
        self::assertEquals($node, $clone);
    }

    public function testJsonSerialize(): void
    {
        $json = /** @lang JSON */ '{"kind":"NonNullType","type":{"kind":"NamedType","name":{"kind":"Name","value":"Foo"}}}';
        $node = new NonNullTypeNode([
            'type' => new NamedTypeNode([
                'name' => new NameNode(['value' => 'Foo']),
            ]),
        ]);

        self::assertJsonStringEqualsJsonString($json, json_encode($node, JSON_THROW_ON_ERROR));
        self::assertEquals($node, AST::fromArray(json_decode($json, true)));
    }

    public function testToString(): void
    {
        self::assertJsonStringEqualsJsonString(
            /** @lang JSON */
            '{"kind":"Name","value":"foo"}',
            (string) new NameNode(['value' => 'foo'])
        );
    }
}
