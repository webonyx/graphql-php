<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\SingleFieldSubscription;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
final class SingleFieldSubscriptionsTest extends ValidatorTestCase
{
    /** @see it('valid subscription') */
    public function testValidSingleFieldSubscription(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe {
          meows
        }
      }
        '
        );
    }

    public function testValidSingleFieldBulkSubscriptions(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe {
          meows
        }
      }

      subscription sub2 {
        barkSubscribe {
          barks
        }
      }
        '
        );
    }

    public function testValidSingleFieldAnonymousSubscription(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription {
        catSubscribe {
          meows
        }
      }
        '
        );
    }

    public function testValidSingleFieldSubscriptionWithMultipleResultFields(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription {
        catSubscribe {
          meows
          meowVolume
        }
      }
        '
        );
    }

    /** @see it('valid subscription with fragment') */
    public function testValidSubscriptionWithFragment(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...fragA
      }
      fragment fragA on SubscriptionRoot {
        catSubscribe {
          meows
        }
      }
        '
        );
    }

    /** @see it('valid subscription with fragment and field') */
    public function testValidSubscriptionWithFragmentAndField(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...fragA
        catSubscribe {
          meows
        }
      }
      fragment fragA on SubscriptionRoot {
        catSubscribe {
          meows
        }
      }
        '
        );
    }

    public function testValidSubscriptionWithInlineFragment(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ... on SubscriptionRoot {
          catSubscribe {
            meows
          }
        }
      }
        '
        );
    }

    /** @see it('fails with more than one root field') */
    public function testInvalidMultipleFieldSubscription(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe {
          meows
        }
        barkSubscribe {
          barks
        }
      }
        ',
            [$this->multipleFieldsInOperation('sub', [6, 9])]
        );
    }

    /** @see it('fails with more than one root field in anonymous subscriptions') */
    public function testInvalidMultipleFieldAnonymousSubscription(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription {
        catSubscribe {
          meows
        }
        barkSubscribe {
          barks
        }
      }
        ',
            [$this->multipleFieldsInOperation(null, [6, 9])]
        );
    }

    /** @see it('fails with many more than one root field') */
    public function testInvalidManyFieldsSubscription(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        first: catSubscribe {
          meows
        }
        second: catSubscribe {
          meows
        }
        third: catSubscribe {
          meows
        }
      }
        ',
            [$this->multipleFieldsInOperation('sub', [6, 9], [9, 9])]
        );
    }

    public function testInvalidManyFieldAnonymousSubscription(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription {
        first: catSubscribe {
          meows
        }
        second: catSubscribe {
          meows
        }
        third: catSubscribe {
          meows
        }
      }
        ',
            [$this->multipleFieldsInOperation(null, [6, 9], [9, 9])]
        );
    }

    /** @see it('fails with many more than one root field via fragments') */
    public function testInvalidManyFieldsViaFragments(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...fragA
        ...fragB
      }
      fragment fragA on SubscriptionRoot {
        catSubscribe {
          meows
        }
      }
      fragment fragB on SubscriptionRoot {
        barkSubscribe {
          barks
        }
      }
        ',
            [$this->multipleFieldsInOperation('sub', [12, 9])]
        );
    }

    /** @see it('does not infinite loop on recursive fragments') */
    public function testDoesNotInfiniteLoopOnRecursiveFragments(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...fragA
      }
      fragment fragA on SubscriptionRoot {
        catSubscribe {
          meows
        }
        ...fragA
        ...fragB
      }
      fragment fragB on SubscriptionRoot {
        barkSubscribe {
          barks
        }
        ...fragA
      }
        ',
            [$this->multipleFieldsInOperation('sub', [13, 9])]
        );
    }

    /** @see it('fails with introspection field') */
    public function testFailsWithIntrospectionField(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        __typename
      }
        ',
            [$this->introspectionFieldInOperation('sub', [3, 9])]
        );
    }

    /** @see it('fails with introspection field in anonymous subscription') */
    public function testFailsWithIntrospectionFieldAnonymous(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription {
        __typename
      }
        ',
            [$this->introspectionFieldInOperation(null, [3, 9])]
        );
    }

    /** @see it('fails with more than one root field including introspection') */
    public function testFailsWithMoreThanOneRootFieldIncludingIntrospection(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe {
          meows
        }
        __typename
      }
        ',
            [
                $this->multipleFieldsInOperation('sub', [6, 9]),
                $this->introspectionFieldInOperation('sub', [6, 9]),
            ]
        );
    }

    /** @see it('fails with more than one root field including aliased introspection via fragment') */
    public function testFailsWithAliasedIntrospectionViaFragment(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...fragA
      }
      fragment fragA on SubscriptionRoot {
        catSubscribe {
          meows
        }
        name: __typename
      }
        ',
            [
                $this->multipleFieldsInOperation('sub', [9, 9]),
                $this->introspectionFieldInOperation('sub', [9, 9]),
            ]
        );
    }

    /** @see it('fails with @skip or @include directive') */
    public function testFailsWithSkipDirective(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe @skip(if: true) {
          meows
        }
      }
        ',
            [$this->skipIncludeInOperation('sub', [3, 22])]
        );
    }

    public function testFailsWithIncludeDirective(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe @include(if: true) {
          meows
        }
      }
        ',
            [$this->skipIncludeInOperation('sub', [3, 22])]
        );
    }

    /** @see it('fails with @skip or @include directive in anonymous subscription') */
    public function testFailsWithSkipDirectiveAnonymous(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription {
        catSubscribe @skip(if: true) {
          meows
        }
      }
        ',
            [$this->skipIncludeInOperation(null, [3, 22])]
        );
    }

    public function testFailsWithSkipDirectiveViaFragment(): void
    {
        $this->expectFailsRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ...frag
      }
      fragment frag on SubscriptionRoot {
        catSubscribe @include(if: true) {
          meows
        }
      }
        ',
            [$this->skipIncludeInOperation('sub', [6, 22])]
        );
    }

    public function testValidSubscriptionWithInlineFragmentWithoutTypeCondition(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        ... {
          catSubscribe {
            meows
          }
        }
      }
        '
        );
    }

    public function testIgnoresFieldsFromNonMatchingFragmentTypeCondition(): void
    {
        $this->expectPassesRule(
            new SingleFieldSubscription(),
            '
      subscription sub {
        catSubscribe {
          meows
        }
        ... on Cat {
          meows
        }
      }
        '
        );
    }

    /** @see it('skips if not subscription type') */
    public function testSkipsIfNotSubscriptionType(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'QueryRoot',
                'fields' => [
                    'foo' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $this->expectPassesRuleWithSchema(
            $schema,
            new SingleFieldSubscription(),
            '
      subscription {
        foo
        bar
      }
        '
        );
    }

    /**
     * @param array<int, int> ...$locations A tuple of line and column
     *
     * @phpstan-return ErrorArray
     */
    private function multipleFieldsInOperation(?string $operationName, array ...$locations): array
    {
        return ErrorHelper::create(
            SingleFieldSubscription::multipleFieldsInOperation($operationName),
            array_map(static function (array $location): SourceLocation {
                [$line, $column] = $location;

                return new SourceLocation($line, $column);
            }, $locations)
        );
    }

    /**
     * @param array<int, int> ...$locations A tuple of line and column
     *
     * @phpstan-return ErrorArray
     */
    private function introspectionFieldInOperation(?string $operationName, array ...$locations): array
    {
        return ErrorHelper::create(
            SingleFieldSubscription::introspectionFieldInOperation($operationName),
            array_map(static function (array $location): SourceLocation {
                [$line, $column] = $location;

                return new SourceLocation($line, $column);
            }, $locations)
        );
    }

    /**
     * @param array<int, int> ...$locations A tuple of line and column
     *
     * @phpstan-return ErrorArray
     */
    private function skipIncludeInOperation(?string $operationName, array ...$locations): array
    {
        return ErrorHelper::create(
            SingleFieldSubscription::skipIncludeInOperation($operationName),
            array_map(static function (array $location): SourceLocation {
                [$line, $column] = $location;

                return new SourceLocation($line, $column);
            }, $locations)
        );
    }
}
