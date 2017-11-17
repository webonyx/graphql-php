<?php
/**
 * FindBreakingChanges tests
 */

namespace GraphQL\Tests\Utils;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\FindBreakingChanges;

class FindBreakingChangesTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->queryType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string()
                ]
            ]
        ]);
    }

    public function testShouldDetectIfTypeWasRemoved()
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $type1,
                    'type2' => $type2
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type2' => $type2
                ]
            ])
        ]);

        $this->assertEquals(['type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED, 'description' => 'Type1 was removed.'],
            FindBreakingChanges::findRemovedTypes($oldSchema, $newSchema)[0]
        );

        $this->assertEquals([], FindBreakingChanges::findRemovedTypes($oldSchema, $oldSchema));
    }

    public function testShouldDetectTypeChanges()
    {
        $objectType = new ObjectType([
            'name' => 'ObjectType',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);

        $interfaceType = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()]
            ]
        ]);

        $unionType = new UnionType([
            'name' => 'Type1',
            'types' => [new ObjectType(['name' => 'blah'])],
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $interfaceType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $unionType
                ]
            ])
        ]);

        $this->assertEquals(
            ['type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_CHANGED, 'description' => 'Type1 changed from an Interface type to a Union type.'],
            FindBreakingChanges::findTypesThatChangedKind($oldSchema, $newSchema)[0]
        );
    }

}