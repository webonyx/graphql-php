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
    public function testVariableDefinitionArrayFromArray(): void
    {
        $res = AST::fromArray([
            'kind' => 'VariableDefinition',
            'directives' => [],
        ]);
		assert($res instanceof  VariableDefinitionNode);
		self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'OperationDefinition',
            'directives' => [],
        ]);
		assert($res instanceof  OperationDefinitionNode);
		self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'Field',
            'directives' => [],
            'arguments' => [],
        ]);
		assert($res instanceof  FieldNode);
		self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->arguments, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentSpread',
            'directives' => [],
        ]);
		assert($res instanceof  FragmentSpreadNode);
		self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentDefinition',
            'directives' => [],
        ]);
		assert($res instanceof  FragmentDefinitionNode);
		self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'InlineFragment',
            'directives' => [],
        ]);
		assert($res instanceof  InlineFragmentNode);
		self::assertEquals($res->directives, new NodeList([]));
    }

    public function testVariableDefinitionArrayFromSparseArray(): void
    {
        $res = AST::fromArray([
            'kind' => 'VariableDefinition',
        ]);
		assert($res instanceof  VariableDefinitionNode);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'OperationDefinition',
        ]);
		assert($res instanceof  OperationDefinitionNode);
        self::assertEquals($res->directives, new NodeList([]));

		$res = AST::fromArray([
			'kind' => 'Field',
		]);
		assert($res instanceof  FieldNode);
		self::assertEquals($res->directives, new NodeList([]));
        self::assertEquals($res->arguments, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentSpread',
        ]);
		assert($res instanceof  FragmentSpreadNode);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'FragmentDefinition',
        ]);
		assert($res instanceof  FragmentDefinitionNode);
        self::assertEquals($res->directives, new NodeList([]));

        $res = AST::fromArray([
            'kind' => 'InlineFragment',
        ]);
		assert($res instanceof  InlineFragmentNode);
		self::assertEquals($res->directives, new NodeList([]));
    }
}
