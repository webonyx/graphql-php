<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class ExecutorLazySchemaTest extends \PHPUnit_Framework_TestCase
{
    public function testWarnsAboutSlowIsTypeOfForLazySchema()
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()]
                ];
            }
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()],
                    'woofs' => ['type' => Type::boolean()]
                ];
            }
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                ];
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        }
                    ]
                ]
            ]),
            'types' => [$catType, $dogType],
            'typeLoader' => function($name) use ($dogType, $petType, $catType) {
                switch ($name) {
                    case 'Dog':
                        return $dogType;
                    case 'Pet':
                        return $petType;
                    case 'Cat':
                        return $catType;
                }
            }
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false]
            ],
        ]);

        Warning::suppress(Warning::FULL_SCHEMA_SCAN_WARNING);
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertEquals($expected, $result);

        Warning::enable(Warning::FULL_SCHEMA_SCAN_WARNING);
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertEquals(1, count($result->errors));
        $this->assertInstanceOf('PHPUnit_Framework_Error_Warning', $result->errors[0]->getPrevious());

        $this->assertEquals(
            'GraphQL Interface Type `Pet` returned `null` from it`s `resolveType` function for value: instance of '.
            'GraphQL\Tests\Executor\Dog. Switching to slow resolution method using `isTypeOf` of all possible '.
            'implementations. It requires full schema scan and degrades query performance significantly.  '.
            'Make sure your `resolveType` always returns valid implementation or throws.',
            $result->errors[0]->getMessage());
    }

    public function testHintsOnConflictingTypeInstancesInDefinitions()
    {
        $calls = [];
        $typeLoader = function($name) use (&$calls) {
            $calls[] = $name;
            switch ($name) {
                case 'Test':
                    return new ObjectType([
                        'name' => 'Test',
                        'fields' => function() {
                            return [
                                'test' => Type::string(),
                            ];
                        }
                    ]);
                default:
                    return null;
            }
        };
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => function() use ($typeLoader) {
                return [
                    'test' => $typeLoader('Test')
                ];
            }
        ]);
        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader
        ]);

        $query = '
            {
                test {
                    test
                }
            }
        ';

        $this->assertEquals([], $calls);
        $result = Executor::execute($schema, Parser::parse($query), ['test' => ['test' => 'value']]);
        $this->assertEquals(['Test', 'Test'], $calls);

        $this->assertEquals(
            'Schema must contain unique named types but contains multiple types named "Test". '.
            'Make sure that type loader returns the same instance as defined in Query.test',
            $result->errors[0]->getMessage()
        );
        $this->assertInstanceOf(
            InvariantViolation::class,
            $result->errors[0]->getPrevious()
        );
    }
}
