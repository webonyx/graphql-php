<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

use function array_keys;

trait HasFieldsTypeImpl
{
    /**
     * Lazily initialized.
     *
     * @var FieldDefinition[]|null
     */
    private $fields;

    /**
     * @throws InvariantViolation
     */
    public function getField(string $name): FieldDefinition
    {
        Utils::invariant($this->hasField($name), 'Field "%s" is not defined for type "%s"', $name, $this->name);

        return $this->tryGetField($name);
    }

    public function tryGetField(string $name): ?FieldDefinition
    {
        $this->initializeFields();

        if (! isset($this->fields[$name])) {
            return null;
        }

        if ($this->fields[$name] instanceof UnresolvedFieldDefinition) {
            $this->fields[$name] = $this->fields[$name]->resolve();
        }

        return $this->fields[$name];
    }

    public function hasField(string $name): bool
    {
        $this->initializeFields();

        return isset($this->fields[$name]);
    }

    /**
     * @return FieldDefinition[]
     *
     * @throws InvariantViolation
     */
    public function getFields(): array
    {
        $this->initializeFields();

        foreach ($this->fields as $name => $field) {
            if (! ($field instanceof UnresolvedFieldDefinition)) {
                continue;
            }

            $this->fields[$name] = $field->resolve();
        }

        return $this->fields;
    }

    /**
     * @return string[]
     *
     * @throws InvariantViolation
     */
    public function getFieldNames(): array
    {
        $this->initializeFields();

        return array_keys($this->fields);
    }

    protected function initializeFields(): void
    {
        if ($this->fields !== null) {
            return;
        }

        $fields       = $this->config['fields'] ?? [];
        $this->fields = FieldDefinition::defineFieldMap($this, $fields);
    }
}
