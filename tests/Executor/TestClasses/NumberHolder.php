<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class NumberHolder
{
    public float $theNumber;

    public function __construct(float $originalNumber)
    {
        $this->theNumber = $originalNumber;
    }
}
