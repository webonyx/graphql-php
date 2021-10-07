<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

class FieldDefinition
{
    public string $name;

    /** @var array<int, FieldArgument> */
    public array $args;

    /**
     * Callback for resolving field value given parent value.
     * Mutually exclusive with `map`
     *
     * @var callable|null
     */
    public $resolveFn;

    /**
     * Callback for mapping list of parent values to list of field values.
     * Mutually exclusive with `resolve`
     *
     * @var callable|null
     */
    public $mapFn;

    public ?string $description;

    public ?string $deprecationReason;

    public ?FieldDefinitionNode $astNode;

    /**
     * Original field definition config
     *
     * @var array<string, mixed>
     */
    public array $config;

    /** @var OutputType&Type */
    private Type $type;

    /** @var callable(int, array<string, mixed>): int|null */
    public $complexityFn;

    /**
     * @param array<string, mixed> $config
     */
    protected function __construct(array $config)
    {
        $this->name              = $config['name'];
        $this->resolveFn         = $config['resolve'] ?? null;
        $this->mapFn             = $config['map'] ?? null;
        $this->args              = isset($config['args'])
            ? FieldArgument::createMap($config['args'])
            : [];
        $this->description       = $config['description'] ?? null;
        $this->deprecationReason = $config['deprecationReason'] ?? null;
        $this->astNode           = $config['astNode'] ?? null;
        $this->complexityFn      = $config['complexity'] ?? null;

        $this->config = $config;
    }

    /**
     * @param (callable():mixed[])|mixed[] $fields
     *
     * @return array<string, self>
     */
    public static function defineFieldMap(Type $type, $fields): array
    {
        if (is_callable($fields)) {
            $fields = $fields();
        }

        if (! is_array($fields)) {
            throw new InvariantViolation(
                sprintf('%s fields must be an array or a callable which returns such an array.', $type->name)
            );
        }

        $map = [];
        foreach ($fields as $name => $field) {
            if (is_array($field)) {
                if (! isset($field['name'])) {
                    if (! is_string($name)) {
                        throw new InvariantViolation(
                            sprintf(
                                '%s fields must be an associative array with field names as keys or a function which returns such an array.',
                                $type->name
                            )
                        );
                    }

                    $field['name'] = $name;
                }

                if (isset($field['args']) && ! is_array($field['args'])) {
                    throw new InvariantViolation(
                        sprintf('%s.%s args must be an array.', $type->name, $name)
                    );
                }

                $fieldDef = self::create($field);
            } elseif ($field instanceof self) {
                $fieldDef = $field;
            } elseif (is_callable($field)) {
                if (! is_string($name)) {
                    throw new InvariantViolation(
                        sprintf(
                            '%s lazy fields must be an associative array with field names as keys.',
                            $type->name
                        )
                    );
                }

                $fieldDef = new UnresolvedFieldDefinition($type, $name, $field);
            } else {
                if (! is_string($name) || ! $field) {
                    throw new InvariantViolation(
                        sprintf(
                            '%s.%s field config must be an array, but got: %s',
                            $type->name,
                            $name,
                            Utils::printSafe($field)
                        )
                    );
                }

                $fieldDef = self::create(['name' => $name, 'type' => $field]);
            }

            $map[$fieldDef->getName()] = $fieldDef;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function create(array $field): FieldDefinition
    {
        return new self($field);
    }

    public function getArg(string $name): ?FieldArgument
    {
        foreach ($this->args as $arg) {
            /** @var FieldArgument $arg */
            if ($arg->name === $name) {
                return $arg;
            }
        }

        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type
    {
        if (! isset($this->type)) {
            /**
             * TODO: replace this phpstan cast with native assert
             *
             * @var Type&OutputType
             */
            $type       = Schema::resolveType($this->config['type']);
            $this->type = $type;
        }

        return $this->type;
    }

    public function __isset(string $name): bool
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public getter for 'type' on FieldDefinition has been deprecated and will be removed" .
                    " in the next major version. Please update your code to use the 'getType' method.",
                    Warning::WARNING_CONFIG_DEPRECATION
                );

                return isset($this->type);
        }

        return isset($this->$name);
    }

    public function __get(string $name)
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public getter for 'type' on FieldDefinition has been deprecated and will be removed" .
                    " in the next major version. Please update your code to use the 'getType' method.",
                    Warning::WARNING_CONFIG_DEPRECATION
                );

                return $this->getType();

            default:
                return $this->$name;
        }

        return null;
    }

    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public setter for 'type' on FieldDefinition has been deprecated and will be removed" .
                    ' in the next major version.',
                    Warning::WARNING_CONFIG_DEPRECATION
                );
                $this->type = $value;
                break;

            default:
                $this->$name = $value;
                break;
        }
    }

    public function isDeprecated(): bool
    {
        return (bool) $this->deprecationReason;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid(Type $parentType): void
    {
        try {
            Utils::assertValidName($this->name);
        } catch (Error $e) {
            throw new InvariantViolation(sprintf('%s.%s: %s', $parentType->name, $this->name, $e->getMessage()));
        }

        Utils::invariant(
            ! isset($this->config['isDeprecated']),
            sprintf(
                '%s.%s should provide "deprecationReason" instead of "isDeprecated".',
                $parentType->name,
                $this->name
            )
        );

        $type = $this->getType();
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }

        Utils::invariant(
            $type instanceof OutputType,
            sprintf(
                '%s.%s field type must be Output Type but got: %s',
                $parentType->name,
                $this->name,
                Utils::printSafe($this->type)
            )
        );
        Utils::invariant(
            $this->resolveFn === null || is_callable($this->resolveFn),
            sprintf(
                '%s.%s field resolver must be a function if provided, but got: %s',
                $parentType->name,
                $this->name,
                Utils::printSafe($this->resolveFn)
            )
        );

        foreach ($this->args as $fieldArgument) {
            $fieldArgument->assertValid($this, $type);
        }
    }
}
