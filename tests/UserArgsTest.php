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
     * @see it('disallows non-existent scalar field on input')
     */
    public function testDisallowsNonExistentScalarFieldOnInput(): void
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
     * @see it('disallows non-existent array field on input')
     */
    public function testDisallowsNonExistentArrayFieldOnInput(): void
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
