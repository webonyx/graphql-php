<?php declare(strict_types=1);

namespace GraphQL\Exception;

/**
 * Allows lazy calculation of a complex string.
 *
 * @phpstan-type MakeString callable(): string
 */
class LazyString
{
    /**
     * @var MakeString
     */
    private $makeString;

    /**
     * @param MakeString $makeString
     */
    public function __construct(callable $makeString)
    {
        $this->makeString = $makeString;
    }

    public function __toString(): string
    {
        return ($this->makeString)();
    }
}
