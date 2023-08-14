<?php declare(strict_types=1);

namespace GraphQL\Type\Registry;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;

/**
 * A registry that returns standard types.
 */
interface StandardTypeRegistry
{
    /** @throws InvariantViolation */
    public function int(): ScalarType;

    /** @throws InvariantViolation */
    public function float(): ScalarType;

    /** @throws InvariantViolation */
    public function id(): ScalarType;

    /** @throws InvariantViolation */
    public function string(): ScalarType;

    /** @throws InvariantViolation */
    public function boolean(): ScalarType;

    /**
     * @phpstan-param value-of<Type::STANDARD_TYPE_NAMES> $type
     *
     * @throws InvariantViolation
     */
    public function standardType(string $type): ScalarType;

    /**
     * Returns all builtin scalar types.
     *
     * @throws InvariantViolation
     *
     * @return array<string, ScalarType>
     */
    public function standardTypes(): array;

    public function introspection(): Introspection;
}
