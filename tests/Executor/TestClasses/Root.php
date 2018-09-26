<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use Exception;
use GraphQL\Deferred;

class Root
{
    /** @var NumberHolder */
    public $numberHolder;

    public function __construct(float $originalNumber)
    {
        $this->numberHolder = new NumberHolder($originalNumber);
    }

    public function promiseToChangeTheNumber($newNumber) : Deferred
    {
        return new Deferred(function () use ($newNumber) {
            return $this->immediatelyChangeTheNumber($newNumber);
        });
    }

    public function immediatelyChangeTheNumber($newNumber) : NumberHolder
    {
        $this->numberHolder->theNumber = $newNumber;

        return $this->numberHolder;
    }

    public function failToChangeTheNumber() : void
    {
        throw new Exception('Cannot change the number');
    }

    public function promiseAndFailToChangeTheNumber() : Deferred
    {
        return new Deferred(function () {
            $this->failToChangeTheNumber();
        });
    }
}
