<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use function array_keys;
use GraphQL\Utils\Utils;

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

        $this->fields = FieldDefinition::defineFieldMap($this, $this->config['fields']);
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

        $field = $this->fields[$name];
        if ($field instanceof UnresolvedFieldDefinition) {
            return $this->fields[$name] = $field->resolve();
        }

        return $field;
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
            if ($field instanceof UnresolvedFieldDefinition) {
                $this->fields[$name] = $field->resolve();
            }
        }

        // @phpstan-ignore-next-line all field definitions are now resolved
        return $this->fields;
    }

    public function getFieldNames(): array
    {
        $this->initializeFields();

        return array_keys($this->fields);
    }
}
