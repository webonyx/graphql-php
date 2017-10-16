<?php
/**
 * @author: Ivo Meißner
 * Date: 03.05.16
 * Time: 13:14
 */
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class LazyInterfaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var InterfaceType
     */
    protected $lazyInterface;

    /**
     * @var ObjectType
     */
    protected $testObject;

    /**
     * Setup schema
     */
    protected function setUp()
    {
        $query = new ObjectType([
            'name' => 'query',
            'fields' => function () {
                return [
                    'lazyInterface' => [
                        'type' => $this->getLazyInterfaceType(),
                        'resolve' => function() {
                            return [];
                        }
                    ]
                ];
            }
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
        if (!$this->lazyInterface) {
            $this->lazyInterface = new InterfaceType([
                'name' => 'LazyInterface',
                'fields' => [
                    'a' => Type::string()
                ],
                'resolveType' => function() {
                    return $this->getTestObjectType();
                },
            ]);
        }

        return $this->lazyInterface;
    }

    /**
     * Returns the test ObjectType
     * @return ObjectType
     */
    protected function getTestObjectType()
    {
        if (!$this->testObject) {
            $this->testObject = new ObjectType([
                'name' => 'TestObject',
                'fields' => [
                    'name' => [
                        'type' => Type::string(),
                        'resolve' => function() {
                            return 'testname';
                        }
                    ]
                ],
                'interfaces' => [$this->getLazyInterfaceType()]
            ]);
        }

        return $this->testObject;
    }

    /**
     * Handles execution of a lazily created interface
     */
    public function testReturnsFragmentsWithLazyCreatedInterface()
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
                'lazyInterface' => [
                    'name' => 'testname'
                ]
            ]
        ];

        $this->assertEquals($expected, Executor::execute($this->schema, Parser::parse($request))->toArray());
    }
}
