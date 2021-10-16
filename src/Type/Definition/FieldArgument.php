<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

class FieldArgument
{
    public string $name;

    /** @var mixed */
    public $defaultValue;

    public ?string $description;

    public ?InputValueDefinitionNode $astNode;

    /** @var array<string, mixed> */
    public array $config;

    /** @var Type&InputType */
    private Type $type;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->name         = $config['name'];
        $this->defaultValue = $config['defaultValue'] ?? null;
        $this->description  = $config['description'] ?? null;
        $this->astNode      = $config['astNode'] ?? null;

        $this->config = $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<int, FieldArgument>
     */
    public static function createMap(array $config): array
    {
        $map = [];
        foreach ($config as $name => $argConfig) {
            if (! is_array($argConfig)) {
                $argConfig = ['type' => $argConfig];
            }

            $map[] = new self($argConfig + ['name' => $name]);
        }

        return $map;
    }

    public function getType(): Type
    {
        if (! isset($this->type)) {
            /**
             * TODO: replace this phpstan cast with native assert
             *
             * @var Type&InputType
             */
            $type       = Schema::resolveType($this->config['type']);
            $this->type = $type;
        }

        return $this->type;
    }

    public function defaultValueExists(): bool
    {
        return array_key_exists('defaultValue', $this->config);
    }

    public function isRequired(): bool
    {
        return $this->getType() instanceof NonNull
            && ! $this->defaultValueExists();
    }

    public function assertValid(FieldDefinition $parentField, Type $parentType): void
    {
        try {
            Utils::assertValidName($this->name);
        } catch (InvariantViolation $e) {
            throw new InvariantViolation(
                sprintf('%s.%s(%s:) %s', $parentType->name, $parentField->name, $this->name, $e->getMessage())
            );
        }

        $type = $this->getType();
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }

        Utils::invariant(
            $type instanceof InputType,
            sprintf(
                '%s.%s(%s): argument type must be Input Type but got: %s',
                $parentType->name,
                $parentField->name,
                $this->name,
                Utils::printSafe($this->type)
            )
        );
        Utils::invariant(
            $this->description === null || is_string($this->description),
            sprintf(
                '%s.%s(%s): argument description type must be string but got: %s',
                $parentType->name,
                $parentField->name,
                $this->name,
                Utils::printSafe($this->description)
            )
        );
    }
}
