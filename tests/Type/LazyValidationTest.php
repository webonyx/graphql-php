<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Additional tests that ensure the proper usage of lazy type loading in the schema.
 */
class LazyValidationTest extends ValidationTest
{
    public function testRejectsDifferentInstancesOfTheSameType(): void
    {
        // Invalid: always creates new instance vs returning one from registry
        $typeLoader = static function ($name): ?ObjectType {
            switch ($name) {
                case 'Query':
                    return new ObjectType([
                        'name' => 'Query',
                        'fields' => [
                            'test' => Type::string(),
                        ],
                    ]);

                default:
                    return null;
            }
        };

        $schema = new Schema([
            'query' => $typeLoader('Query'),
            'typeLoader' => $typeLoader,
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Type loader returns different instance for Query than field/argument definitions. '
            . 'Make sure you always return the same instance for the same type name.'
        );
        $schema->assertValid();
    }
}
