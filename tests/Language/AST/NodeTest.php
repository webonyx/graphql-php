<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language\AST;

use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \GraphQL\Language\AST\Node
 */
class NodeTest extends TestCase
{
    /**
     * @covers ::cloneDeep
     */
    public function testCloneDeep(): void
    {
        $node  = Parser::objectTypeDefinition(
            <<<'GRAPHQL'
            type Test {
                id: ID!
            }
            GRAPHQL,
        );
        $clone = $node->cloneDeep();

        $this->assertNotSame($node, $clone);
        $this->assertNotSame($node->directives, $clone->directives);
    }
}
