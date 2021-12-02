<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;

use function is_array;

class UnresolvedFieldDefinition
{
    /** @var ObjectType|InterfaceType */
    private Type $parentType;

    private string $name;

    /** @var callable(): (FieldDefinition|array<string, mixed>|Type) $resolver */
    private $resolver;

    /**
     * @param ObjectType|InterfaceType                                $parentType
     * @param callable(): (FieldDefinition|array<string, mixed>|Type) $resolver
     */
    public function __construct(Type $parentType, string $name, callable $resolver)
    {
        $this->parentType = $parentType;
        $this->name       = $name;
        $this->resolver   = $resolver;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function resolve(): FieldDefinition
    {
        $field = ($this->resolver)();

        if ($field instanceof FieldDefinition) {
            if ($field->name !== $this->name) {
                throw new InvariantViolation(
                    "{$this->parentType->name}.{$this->name} should not dynamically change its name when resolved lazily."
                );
            }

            return $field;
        }

        if (! is_array($field)) {
            return FieldDefinition::create(['name' => $this->name, 'type' => $field]);
        }

        if (! isset($field['name'])) {
            $field['name'] = $this->name;
        } elseif ($field['name'] !== $this->name) {
            throw new InvariantViolation(
                "{$this->parentType->name}.{$this->name} should not dynamically change its name when resolved lazily."
            );
        }

        if (isset($field['args']) && ! is_array($field['args'])) {
            throw new InvariantViolation(
                "{$this->parentType->name}.{$this->name} args must be an array."
            );
        }

        return FieldDefinition::create($field);
    }
}
