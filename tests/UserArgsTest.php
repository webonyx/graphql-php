<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class UserArgsTest extends TestCase
{
    public function testErrorForNonExistentScalarInputField(): void
    {
        $query = '
        query {
            x: getDummyValue(args: {
                int: 0
                scalar: 0
            })
        }
        ';
        $result = GraphQL::executeQuery($this->schema(), $query)->toArray();
        self::assertSame('Field "scalar" is not defined by type "InputType".', $result['errors'][0]['message'] ?? null);
    }

    public function testErrorForNonExistentArrayInputField(): void
    {
        $query = '
        query {
            x: getDummyValue(args: {
                int: 0
                array: []
            })
        }
        ';
        $result = GraphQL::executeQuery($this->schema(), $query)->toArray();
        self::assertSame('Field "array" is not defined by type "InputType".', $result['errors'][0]['message'] ?? null);
    }

    /** @throws InvariantViolation */
    private function schema(): Schema
    {
        $inputType = new InputObjectType([
            'name' => 'InputType',
            'fields' => ['int' => ['type' => Type::int()]],
        ]);
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'getDummyValue' => [
                    'type' => Type::string(),
                    'args' => ['args' => ['type' => $inputType]],
                    'resolve' => static fn (): string => 'dummy value',
                ],
            ],
        ]);

        return new Schema(['query' => $query]);
    }
}
