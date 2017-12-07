<?php
namespace GraphQL\Tests;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class LazySchema
 * @package GraphQL\Tests
 */
class LazySchema {
	private $types = [];

	const SELF_RESOLVED_EXAMPLE = 5;
	const IN_PLACE_RESOLVED_EXAMPLE = 10;

	/**
	 * @param $name
	 *
	 * @return ObjectType
	 */
	public function get($name) {
		if (!isset($this->types[$name])) {
			$this->types[$name] = $this->{$name}();
		}
		return $this->types[$name];
	}

	private function Query() {
		return new ObjectType([
			'name' => 'QueryType',
			'fields' => function() {
				return [
					'inPlaceResolved' => [
						'type' => Type::int(),
						'resolve' => function() {
							return self::IN_PLACE_RESOLVED_EXAMPLE;
						}
					],
					'selfResolved' => ['type' => $this->get('SelfResolvedObject')]
				];
			}
		]);
	}

	/**
	 * this type defines it's own resolve function
	 * we will test, whether it is used, when 'resolve' is not defined in the parent type, that is using given type
	 *
	 * @return \GraphQL\Type\Definition\ObjectType
	 */
	private function SelfResolvedObject() {
		return new ObjectType([
				'name' => 'SelfResolvedObject',
				'description' => 'This object has a resolve function and we want it to be used when resolve is not defined in the parent object',
				'fields' => function() {
					return [
						'field1' => Type::int()
					];
				},
				'resolve' => function() {
					return [
						'field1' => self::SELF_RESOLVED_EXAMPLE
					];
				}
			]
		);
	}
}