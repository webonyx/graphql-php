<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class LazyInterfaceTest extends TestCase
{
    /** @var Schema */
    protected $schema;

    /** @var InterfaceType */
    protected $lazyInterface;

    /** @var ObjectType */
    protected $testObject;

    /**
     * Handles execution of a lazily created interface
     */
    public function testReturnsFragmentsWithLazyCreatedInterface() : void
    {
        $request = '
        {
            lazyInterface {
                ... on TestObject {
                    name
                }
            }
        }
        ';

        $expected = [
            'data' => [
                'lazyInterface' => ['name' => 'testname'],
            ],
        ];

        self::assertEquals($expected, Executor::execute($this->schema, Parser::parse($request))->toArray());
    }

    /**
     * Setup schema
     */
    protected function setUp() : void
    {
        $query = new ObjectType([
            'name'   => 'query',
            'fields' => function () {
                return [
                    'lazyInterface' => [
                        'type'    => $this->getLazyInterfaceType(),
                        'resolve' => static function () : array {
                            return [];
                        },
                    ],
                ];
            },
        ]);

        $this->schema = new Schema(['query' => $query, 'types' => [$this->getTestObjectType()]]);
    }

    /**
     * Returns the LazyInterface
     *
     * @return InterfaceType
     */
    protected function getLazyInterfaceType()
    {
        if (! $this->lazyInterface) {
            $this->lazyInterface = new InterfaceType([
                'name'        => 'LazyInterface',
                'fields'      => [
                    'a' => Type::string(),
                ],
                'resolveType' => function () : ObjectType {
                    return $this->getTestObjectType();
                },
            ]);
        }

        return $this->lazyInterface;
    }

    /**
     * Returns the test ObjectType
     *
     * @return ObjectType
     */
    protected function getTestObjectType()
    {
        if (! $this->testObject) {
            $this->testObject = new ObjectType([
                'name'       => 'TestObject',
                'fields'     => [
                    'name' => [
                        'type'    => Type::string(),
                        'resolve' => static function () : string {
                            return 'testname';
                        },
                    ],
                ],
                'interfaces' => [$this->getLazyInterfaceType()],
            ]);
        }

        return $this->testObject;
    }
}
