<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;

interface HasFieldsType
{
    /**
     * @throws InvariantViolation
     */
    public function getField(string $name) : FieldDefinition;

    public function hasField(string $name) : bool;

    public function findField(string $name) : ?FieldDefinition;

    /**
     * @return array<string, FieldDefinition>
     *
     * @throws InvariantViolation
     */
    public function getFields() : array;

    /**
     * @return array<int, string>
     *
     * @throws InvariantViolation
     */
    public function getFieldNames() : array;
}
