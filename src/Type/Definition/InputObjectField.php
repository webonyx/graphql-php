<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function array_key_exists;
use function sprintf;

class InputObjectField
{
    public string $name;

    /** @var mixed|null */
    public $defaultValue;

    public ?string $description;

    /** @var Type&InputType */
    private Type $type;

    public ?InputValueDefinitionNode $astNode;

    /** @var array<string, mixed> */
    public array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->name         = $config['name'];
        $this->defaultValue = $config['defaultValue'] ?? null;
        $this->description  = $config['description'] ?? null;
        // Do nothing for type, it is lazy loaded in getType()
        $this->astNode = $config['astNode'] ?? null;

        $this->config = $config;
    }

    /**
     * @return Type&InputType
     */
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

        $type = $this->getType();
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }

        Utils::invariant(
            $type instanceof InputType,
            sprintf(
                '%s.%s field type must be Input Type but got: %s',
                $parentType->name,
                $this->name,
                Utils::printSafe($this->type)
            )
        );
        Utils::invariant(
            ! array_key_exists('resolve', $this->config),
            sprintf(
                '%s.%s field has a resolve property, but Input Types cannot define resolvers.',
                $parentType->name,
                $this->name
            )
        );
    }
}
