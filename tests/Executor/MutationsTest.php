<?php
namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class MutationsTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles mutation execution ordering

    /**
     * @it evaluates mutations serially
     */
    public function testEvaluatesMutationsSerially()
    {
        $doc = 'mutation M {
      first: immediatelyChangeTheNumber(newNumber: 1) {
        theNumber
      },
      second: promiseToChangeTheNumber(newNumber: 2) {
        theNumber
      },
      third: immediatelyChangeTheNumber(newNumber: 3) {
        theNumber
      }
      fourth: promiseToChangeTheNumber(newNumber: 4) {
        theNumber
      },
      fifth: immediatelyChangeTheNumber(newNumber: 5) {
        theNumber
      }
    }';
        $ast = Parser::parse($doc);
        $mutationResult = Executor::execute($this->schema(), $ast, new Root(6));
        $expected = [
            'data' => [
                'first' => [
                    'theNumber' => 1
                ],
                'second' => [
                    'theNumber' => 2
                ],
                'third' => [
                    'theNumber' => 3
                ],
                'fourth' => [
                    'theNumber' => 4
                ],
                'fifth' => [
                    'theNumber' => 5
                ]
            ]
        ];
        $this->assertEquals($expected, $mutationResult->toArray());
    }

    /**
     * @it evaluates mutations correctly in the presense of a failed mutation
     */
    public function testEvaluatesMutationsCorrectlyInThePresenseOfAFailedMutation()
    {
        $doc = 'mutation M {
      first: immediatelyChangeTheNumber(newNumber: 1) {
        theNumber
      },
      second: promiseToChangeTheNumber(newNumber: 2) {
        theNumber
      },
      third: failToChangeTheNumber(newNumber: 3) {
        theNumber
      }
      fourth: promiseToChangeTheNumber(newNumber: 4) {
        theNumber
      },
      fifth: immediatelyChangeTheNumber(newNumber: 5) {
        theNumber
      }
      sixth: promiseAndFailToChangeTheNumber(newNumber: 6) {
        theNumber
      }
    }';
        $ast = Parser::parse($doc);
        $mutationResult = Executor::execute($this->schema(), $ast, new Root(6));
        $expected = [
            'data' => [
                'first' => [
                    'theNumber' => 1
                ],
                'second' => [
                    'theNumber' => 2
                ],
                'third' => null,
                'fourth' => [
                    'theNumber' => 4
                ],
                'fifth' => [
                    'theNumber' => 5
                ],
                'sixth' => null,
            ],
            'errors' => [
                [
                    'debugMessage' => 'Cannot change the number',
                    'locations' => [['line' => 8, 'column' => 7]]
                ],
                [
                    'debugMessage' => 'Cannot change the number',
                    'locations' => [['line' => 17, 'column' => 7]]
                ]
            ]
        ];
        $this->assertArraySubset($expected, $mutationResult->toArray(true));
    }

    private function schema()
    {
        $numberHolderType = new ObjectType([
            'fields' => [
                'theNumber' => ['type' => Type::int()],
            ],
            'name' => 'NumberHolder',
        ]);
        $schema = new Schema([
            'query' => new ObjectType([
                'fields' => [
                    'numberHolder' => ['type' => $numberHolderType],
                ],
                'name' => 'Query',
            ]),
            'mutation' => new ObjectType([
                'fields' => [
                    'immediatelyChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => (function (Root $obj, $args) {
                            return $obj->immediatelyChangeTheNumber($args['newNumber']);
                        })
                    ],
                    'promiseToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => (function (Root $obj, $args) {
                            return $obj->promiseToChangeTheNumber($args['newNumber']);
                        })
                    ],
                    'failToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => (function (Root $obj, $args) {
                            return $obj->failToChangeTheNumber($args['newNumber']);
                        })
                    ],
                    'promiseAndFailToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => (function (Root $obj, $args) {
                            return $obj->promiseAndFailToChangeTheNumber($args['newNumber']);
                        })
                    ]
                ],
                'name' => 'Mutation',
            ])
        ]);
        return $schema;
    }
}

class NumberHolder
{
    public $theNumber;

    public function __construct($originalNumber)
    {
        $this->theNumber = $originalNumber;
    }
}

class Root {
    public $numberHolder;

    public function __construct($originalNumber)
    {
        $this->numberHolder = new NumberHolder($originalNumber);
    }

    /**
     * @param $newNumber
     * @return NumberHolder
     */
    public function immediatelyChangeTheNumber($newNumber)
    {
        $this->numberHolder->theNumber = $newNumber;
        return $this->numberHolder;
    }

    /**
     * @param $newNumber
     *
     * @return Deferred
     */
    public function promiseToChangeTheNumber($newNumber)
    {
        return new Deferred(function () use ($newNumber) {
            return $this->immediatelyChangeTheNumber($newNumber);
        });
    }

    /**
     * @throws \Exception
     */
    public function failToChangeTheNumber()
    {
        throw new \Exception('Cannot change the number');
    }

    /**
     * @return Deferred
     */
    public function promiseAndFailToChangeTheNumber()
    {
        return new Deferred(function () {
            throw new \Exception("Cannot change the number");
        });
    }
}
