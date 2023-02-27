<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class AstFromArrayTest extends TestCase
{
    public function testVariableDefinitionArrayFromArray(): void
    {
		/**
		 * @var VariableDefinitionNode
		 */
		$res = AST::fromArray([
				"kind"=>"VariableDefinition",
				"directives"=>[]
		]);
        self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var OperationDefinitionNode
		 */
		$res = AST::fromArray([
			"kind"=>"OperationDefinition",
			"directives"=>[]
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var FieldNode
		 */
		$res = AST::fromArray([
			"kind"=>"Field",
			"directives"=>[],
			"arguments"=>[]
		]);
		self::assertEquals($res->directives, new NodeList([]) );
		self::assertEquals($res->arguments, new NodeList([]) );

		/**
		 * @var FragmentSpreadNode
		 */
		$res = AST::fromArray([
			"kind"=>"FragmentSpread",
			"directives"=>[],
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var FragmentDefinitionNode
		 */
		$res = AST::fromArray([
			"kind"=>"FragmentDefinition",
			"directives"=>[],
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var InlineFragmentNode
		 */
		$res = AST::fromArray([
			"kind"=>"InlineFragment",
			"directives"=>[],
		]);
		self::assertEquals($res->directives, new NodeList([]) );
	}

	public function testVariableDefinitionArrayFromSparseArray(): void
	{
		/**
		 * @var VariableDefinitionNode
		 */
		$res = AST::fromArray([
			"kind"=>"VariableDefinition"
		]);

		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var OperationDefinitionNode
		 */
		$res = AST::fromArray([
			"kind"=>"OperationDefinition"
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var FieldNode
		 */
		$res = AST::fromArray([
			"kind"=>"Field",
		]);
		self::assertEquals($res->directives, new NodeList([]) );
		self::assertEquals($res->arguments, new NodeList([]) );

		/**
		 * @var FragmentSpreadNode
		 */
		$res = AST::fromArray([
			"kind"=>"FragmentSpread",
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var FragmentDefinitionNode
		 */
		$res = AST::fromArray([
			"kind"=>"FragmentDefinition",
		]);
		self::assertEquals($res->directives, new NodeList([]) );

		/**
		 * @var InlineFragmentNode
		 */
		$res = AST::fromArray([
			"kind"=>"InlineFragment",
		]);
		self::assertEquals($res->directives, new NodeList([]) );
	}
}
