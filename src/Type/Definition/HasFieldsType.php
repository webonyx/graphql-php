<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;

interface HasFieldsType
{
    /** @throws InvariantViolation */
    public function getField(string $name): FieldDefinition;

    public function hasField(string $name): bool;

    public function findField(string $name): ?FieldDefinition;

    /**
     * @throws InvariantViolation
     *
     * @return array<string, FieldDefinition>
     */
    public function getFields(): array;

    /**
     * @throws InvariantViolation
     *
     * @return array<string, FieldDefinition>
     */
    public function getVisibleFields(): array;

    /**
     * Get all field names, including only visible fields.
     *
     * @throws InvariantViolation
     *
     * @return array<int, string>
     */
    public function getFieldNames(): array;
}
