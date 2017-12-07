<?php

namespace GraphQL\Tests;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;

/**
 * Class LazyLoadingTest
 * @package GraphQL\Tests
 *
 */
class LazyLoadingTest extends \PHPUnit_Framework_TestCase{
	private $schema;

	public function setUp() {
		$typeRegistry = new LazySchema();

		$this->schema = new Schema([
			'query' => $typeRegistry->get('Query'),
			'typeLoader' => function ($name) use ($typeRegistry) {
				return $typeRegistry->get($name);
			}
		]);
	}

	public function testResolve() {
		$query = '{
			inPlaceResolved
			selfResolved {
				field1
			}
		}';

		$result = GraphQL::executeQuery($this->schema, $query)->jsonSerialize();
		$data = $result['data'];

		$this->assertEquals(LazySchema::IN_PLACE_RESOLVED_EXAMPLE, $data['inPlaceResolved']);
		$this->assertEquals(LazySchema::SELF_RESOLVED_EXAMPLE, $data['selfResolved']['field1']);
	}
}