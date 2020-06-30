<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\SingleFieldSubscription;
use function array_map;

class SingleFieldSubscriptionsTest extends ValidatorTestCase
{
    /**
     * @see it('valid single field subscription')
     */
    public function testValidSingleFieldSubscription() : void
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

    /**
     * @see it('valid single field bulk subscriptions')
     */
    public function testValidSingleFieldBulkSubscriptions() : void
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
        dogSubscribe {
          barks
        }
      }
        '
        );
    }

    /**
     * @see it('valid single field anonymous subscription')
     */
    public function testValidSingleFieldAnonymousSubscription() : void
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

    /**
     * @see it('valid single field subscription')
     */
    public function testValidSingleFieldSubscriptionWithMultipleResultFields() : void
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

    /**
     * @see it('invalid multiple field subscription')
     */
    public function testInvalidMultipleFieldSubscription() : void
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

    /**
     * @see it('invalid multiple field anonymous subscription')
     */
    public function testInvalidMultipleFieldAnonymousSubscription() : void
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

    /**
     * @see it('invalid many fields subscription')
     */
    public function testInvalidManyFieldsSubscription() : void
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

    /**
     * @see it('invalid many fields anonymous subscription')
     */
    public function testInvalidManyFieldAnonymousSubscription() : void
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

    /**
     * @param array<int, int> ...$locations A tuple of line and column
     */
    private function multipleFieldsInOperation(?string $operationName, array ...$locations)
    {
        return FormattedError::create(
            SingleFieldSubscription::multipleFieldsInOperation($operationName),
            array_map(static function (array $location) : SourceLocation {
                [$line, $column] = $location;

                return new SourceLocation($line, $column);
            }, $locations)
        );
    }
}
