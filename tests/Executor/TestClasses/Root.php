<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Deferred;

final class Root
{
    public NumberHolder $numberHolder;

    public function __construct(float $originalNumber)
    {
        $this->numberHolder = new NumberHolder($originalNumber);
    }

    public function promiseToChangeTheNumber(float $newNumber): Deferred
    {
        return new Deferred(fn (): NumberHolder => $this->immediatelyChangeTheNumber($newNumber));
    }

    public function immediatelyChangeTheNumber(float $newNumber): NumberHolder
    {
        $this->numberHolder->theNumber = $newNumber;

        return $this->numberHolder;
    }

    /**
     * @throws \Exception
     */
    public function failToChangeTheNumber(): void
    {
        throw new \Exception('Cannot change the number');
    }

    public function promiseAndFailToChangeTheNumber(): Deferred
    {
        return new Deferred(function (): void {
            $this->failToChangeTheNumber();
        });
    }
}
