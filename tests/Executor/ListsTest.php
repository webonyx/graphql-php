<?php
namespace GraphQL\Tests\Executor;

use GraphQL\Error\Error;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ListsTest extends \PHPUnit_Framework_TestCase
{
    private function check($testType, $testData, $expected)
    {
        $data = ['test' => $testData];
        $dataType = null;

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => function () use (&$testType, &$dataType, $data) {
                return [
                    'test' => [
                        'type' => $testType
                    ],
                    'nest' => [
                        'type' => $dataType,
                        'resolve' => function () use ($data) {
                            return $data;
                        }
                    ]
                ];
            }
        ]);

        $schema = new Schema([
            'query' => $dataType
        ]);

        $ast = Parser::parse('{ nest { test } }');

        $result = Executor::execute($schema, $ast, $data);
        $this->assertArraySubset($expected, $result->toArray());
    }

    // Describe: Execute: Handles list nullability

    /**
     * @describe [T]
     */
    public function testHandlesNullableLists()
    {
        $type = Type::listOf(Type::int());

        // Contains values
        $this->check(
            $type,
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->check(
            $type,
            [ 1, null, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->check(
            $type,
            null,
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );
    }

    /**
     * @describe [T]!
     */
    public function testHandlesNonNullableLists()
    {
        $type = Type::nonNull(Type::listOf(Type::int()));

        // Contains values
        $this->check(
            $type,
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->check(
            $type,
            [ 1, null, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->check(
            $type,
            null,
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    FormattedError::create(
                        'Cannot return null for non-nullable field DataType.test.',
                        [ new SourceLocation(1, 10) ]
                    )
                ]
            ]
        );
    }

    /**
     * @describe [T!]
     */
    public function testHandlesListOfNonNulls()
    {
        $type = Type::listOf(Type::nonNull(Type::int()));

        // Contains values
        $this->check(
            $type,
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->check(
            $type,
            [ 1, null, 2 ],
            [
                'data' => [ 'nest' => [ 'test' => null ] ],
                'errors' => [
                    FormattedError::create(
                        'Cannot return null for non-nullable field DataType.test.',
                        [ new SourceLocation(1, 10) ]
                    )
                ]
            ]
        );

        // Returns null
        $this->check(
            $type,
            null,
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );
    }

    /**
     * @describe [T!]!
     */
    public function testHandlesNonNullListOfNonNulls()
    {
        $type = Type::nonNull(Type::listOf(Type::nonNull(Type::int())));

        // Contains values
        $this->check(
            $type,
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );


        // Contains null
        $this->check(
            $type,
            [ 1, null, 2 ],
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    FormattedError::create(
                        'Cannot return null for non-nullable field DataType.test.',
                        [ new SourceLocation(1, 10) ]
                    )
                ]
            ]
        );

        // Returns null
        $this->check(
            $type,
            null,
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    FormattedError::create(
                        'Cannot return null for non-nullable field DataType.test.',
                        [ new SourceLocation(1, 10) ]
                    )
                ]
            ]
        );
    }
}
