<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\NumberHolder;
use GraphQL\Tests\Executor\TestClasses\Root;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class MutationsTest extends TestCase
{
    use ArraySubsetAsserts;

    // Execute: Handles mutation execution ordering

    /**
     * @see it('evaluates mutations serially')
     */
    public function testEvaluatesMutationsSerially(): void
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
                'first' => ['theNumber' => 1],
                'second' => ['theNumber' => 2],
                'third' => ['theNumber' => 3],
                'fourth' => ['theNumber' => 4],
                'fifth' => ['theNumber' => 5],
            ],
        ];
        self::assertSame($expected, $mutationResult->toArray());
    }

    private function schema(): Schema
    {
        $numberHolderType = new ObjectType([
            'fields' => [
                'theNumber' => ['type' => Type::int()],
            ],
            'name' => 'NumberHolder',
        ]);

        return new Schema([
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
                        'resolve' => static fn (Root $obj, array $args): NumberHolder => $obj->immediatelyChangeTheNumber($args['newNumber']),
                    ],
                    'promiseToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => static fn (Root $obj, array $args): Deferred => $obj->promiseToChangeTheNumber($args['newNumber']),
                    ],
                    'failToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => static function (Root $obj, array $args): void {
                            $obj->failToChangeTheNumber();
                        },
                    ],
                    'promiseAndFailToChangeTheNumber' => [
                        'type' => $numberHolderType,
                        'args' => ['newNumber' => ['type' => Type::int()]],
                        'resolve' => static fn (Root $obj, array $args): Deferred => $obj->promiseAndFailToChangeTheNumber(),
                    ],
                ],
                'name' => 'Mutation',
            ]),
        ]);
    }

    /**
     * @see it('evaluates mutations correctly in the presense of a failed mutation')
     */
    public function testEvaluatesMutationsCorrectlyInThePresenseOfAFailedMutation(): void
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
                'first' => ['theNumber' => 1],
                'second' => ['theNumber' => 2],
                'third' => null,
                'fourth' => ['theNumber' => 4],
                'fifth' => ['theNumber' => 5],
                'sixth' => null,
            ],
            'errors' => [
                [
                    'locations' => [['line' => 8, 'column' => 7]],
                    'extensions' => ['debugMessage' => 'Cannot change the number'],
                ],
                [
                    'locations' => [['line' => 17, 'column' => 7]],
                    'extensions' => ['debugMessage' => 'Cannot change the number'],
                ],
            ],
        ];
        self::assertArraySubset($expected, $mutationResult->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }
}
