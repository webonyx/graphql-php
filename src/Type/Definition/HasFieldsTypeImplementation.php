<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Utils\Utils;

use function array_keys;

/**
 * @see HasFieldsType
 */
trait HasFieldsTypeImplementation
{
    /**
     * Lazily initialized.
     *
     * @var array<string, FieldDefinition|UnresolvedFieldDefinition>
     */
    private array $fields;

    private function initializeFields(): void
    {
        if (isset($this->fields)) {
            return;
        }

        $fields       = $this->config['fields'] ?? [];
        $this->fields = FieldDefinition::defineFieldMap($this, $fields);
    }

    public function getField(string $name): FieldDefinition
    {
        Utils::invariant($this->hasField($name), 'Field "%s" is not defined for type "%s"', $name, $this->name);

        return $this->findField($name);
    }

    public function findField(string $name): ?FieldDefinition
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

    public function getFieldNames(): array
    {
        $this->initializeFields();

        return array_keys($this->fields);
    }
}
