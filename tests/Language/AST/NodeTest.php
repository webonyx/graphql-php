<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language\AST;

use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
{
    public function testCloneDeep(): void
    {
        $node  = Parser::objectTypeDefinition('
        type Test {
            id(arg: Int): ID!
        }
        ');
        $clone = $node->cloneDeep();

        self::assertNotSameButEquals($node, $clone);
        self::assertNotSameButEquals($node->name, $clone->name);
        self::assertNotSameButEquals($node->directives, $clone->directives);

        $nodeFields  = $node->fields;
        $cloneFields = $clone->fields;
        self::assertNotSameButEquals($nodeFields, $cloneFields);

        $nodeId  = $nodeFields[0];
        $cloneId = $cloneFields[0];
        self::assertNotSameButEquals($nodeId, $cloneId);

        $nodeIdArgs  = $nodeId->arguments;
        $cloneIdArgs = $cloneId->arguments;
        self::assertNotSameButEquals($nodeIdArgs, $cloneIdArgs);

        $nodeArg  = $nodeIdArgs[0];
        $cloneArg = $cloneIdArgs[0];
        self::assertNotSameButEquals($nodeArg, $cloneArg);

        self::assertSame($node->loc, $clone->loc);
    }

    private static function assertNotSameButEquals(object $node, object $clone): void
    {
        self::assertNotSame($node, $clone);
        self::assertEquals($node, $clone);
    }
}
