<?php

// phpcs:disable

declare(strict_types=1);

namespace GraphQL\Tests\PHPUnit\Constraint;

use ArrayObject;
use PHPUnit\Framework\Constraint\Constraint;
use SebastianBergmann\Comparator\ComparisonFailure;
use function array_replace_recursive;
use function is_array;
use function iterator_to_array;
use function var_export;

/**
 * Constraint that asserts that the array it is evaluated for has a specified subset.
 *
 * Uses array_replace_recursive() to check if a key value subset is part of the
 * subject array.
 *
 * Port from dms/phpunit-arraysubset-asserts
 */
final class ArraySubset extends Constraint
{
    /** @var iterable<mixed> */
    private iterable $subset;

    private bool $strict;

    /**
     * @param mixed[] $subset
     */
    public function __construct(iterable $subset, bool $strict = false)
    {
        $this->strict = $strict;
        $this->subset = $subset;
    }

    public function evaluate($other, string $description = '', bool $returnResult = false): ?bool
    {
        //type cast $other & $this->subset as an array to allow
        //support in standard array functions.
        $other        = $this->toArray($other);
        $this->subset = $this->toArray($this->subset);
        $patched      = array_replace_recursive($other, $this->subset);
        if ($this->strict) {
            $result = $other === $patched;
        } else {
            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            $result = $other == $patched;
        }
        if ($returnResult) {
            return $result;
        }
        if ($result) {
            return null;
        }

        $f = new ComparisonFailure(
            $patched,
            $other,
            var_export($patched, true),
            var_export($other, true)
        );
        $this->fail($other, $description, $f);
    }

    public function toString() : string
    {
        return 'has the subset ' . $this->exporter()->export($this->subset);
    }

    /** @inheritDoc */
    protected function failureDescription($other) : string
    {
        return 'an array ' . $this->toString();
    }

    /**
     * @param iterable<mixed> $other
     *
     * @return array<mixed>
     */
    private function toArray(iterable $other) : array
    {
        if (is_array($other)) {
            return $other;
        }

        if ($other instanceof ArrayObject) {
            return $other->getArrayCopy();
        }

        return iterator_to_array($other);
    }
}
