<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use function is_array;
use function sprintf;

class UnresolvedFieldDefinition
{
    /** @var Type $type */
    private $type;

    /** @var string $name */
    private $name;

    /** @var callable(): (FieldDefinition|array<string, mixed>|Type) $resolver */
    private $resolver;

    /**
     * @param callable(): (FieldDefinition|array<string, mixed>|Type) $resolver
     */
    public function __construct(Type $type, string $name, callable $resolver)
    {
        $this->type     = $type;
        $this->name     = $name;
        $this->resolver = $resolver;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function resolve() : FieldDefinition
    {
        $field = ($this->resolver)();

        if ($field instanceof FieldDefinition) {
            if ($field->name !== $this->name) {
                throw new InvariantViolation(
                    sprintf('%s.%s should not dynamically change its name when resolved lazily.', $this->type->name, $this->name)
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
                sprintf('%s.%s should not dynamically change its name when resolved lazily.', $this->type->name, $this->name)
            );
        }

        if (isset($field['args']) && ! is_array($field['args'])) {
            throw new InvariantViolation(
                sprintf('%s.%s args must be an array.', $this->type->name, $this->name)
            );
        }

        return FieldDefinition::create($field);
    }
}
