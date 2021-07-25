<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class UserArgsTest extends TestCase
{
    /**
     * Returns error when a scalar value is provided for a non-existent input field.
     */
    public function testErrorForNonExistentScalarInputField(): void
    {
        $query  = '
        query {
            x: getDummyValue(args: {
                int: 0
                scalar: 0
            })
        }
        ';
        $result = GraphQL::executeQuery($this->schema(), $query)->toArray();
        self::assertEquals('Field "scalar" is not defined by type InputType.', $result['errors'][0]['message']);
    }

    /**
     * Returns error when an array value is provided for a non-existent input field.
     */
    public function testErrorForNonExistentArrayInputField(): void
    {
        $query  = '
        query {
            x: getDummyValue(args: {
                int: 0
                array: []
            })
        }
        ';
        $result = GraphQL::executeQuery($this->schema(), $query)->toArray();
        self::assertEquals('Field "array" is not defined by type InputType.', $result['errors'][0]['message']);
    }

    private function schema(): Schema
    {
        $inputType = new InputObjectType([
            'name'   => 'InputType',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);
        $query     = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'getDummyValue' => [
                    'type' => Type::string(),
                    'args' => ['args' => ['type' => $inputType]],
                    'resolve' => static function (): string {
                        return 'dummy value';
                    },
                ],
            ],
        ]);

        return new Schema(['query' => $query]);
    }
}
