<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class AstFromArrayTest extends TestCase
{
    public function testFromArray(): void
    {
        $res = AST::fromArray([
            'kind' => 'VariableDefinition',
            'directives' => [],
        ]);
        self::assertInstanceOf(VariableDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'OperationDefinition',
            'directives' => [],
        ]);
        self::assertInstanceOf(OperationDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->variableDefinitions, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'Field',
            'directives' => [],
            'arguments' => [],
        ]);
        self::assertInstanceOf(FieldNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->arguments, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentSpread',
            'directives' => [],
        ]);
        self::assertInstanceOf(FragmentSpreadNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentDefinition',
            'directives' => [],
        ]);
        self::assertInstanceOf(FragmentDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'InlineFragment',
            'directives' => [],
        ]);
        self::assertInstanceOf(InlineFragmentNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
    }

    public function testFromOptimizedArray(): void
    {
        $res = AST::fromArray([
            'kind' => 'VariableDefinition',
        ]);
        self::assertInstanceOf(VariableDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'OperationDefinition',
        ]);
        self::assertInstanceOf(OperationDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->variableDefinitions, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'Field',
        ]);
        self::assertInstanceOf(FieldNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->arguments, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentSpread',
        ]);
        self::assertInstanceOf(FragmentSpreadNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentDefinition',
        ]);
        self::assertInstanceOf(FragmentDefinitionNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'InlineFragment',
        ]);
        self::assertInstanceOf(InlineFragmentNode::class, $res);
        self::assertEquals($res->directives, new NodeList([]));
    }
}
