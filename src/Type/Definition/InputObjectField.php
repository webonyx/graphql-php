<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function array_key_exists;

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
             * @see it('rejects an Input Object type with incorrectly typed fields')
             */
             // @phpstan-ignore-next-line schema validation will catch a Type that is not an InputType
            $this->type = Schema::resolveType($this->config['type']);
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
     * @param Type &NamedType $parentType
     *
     * @throws InvariantViolation
     */
    public function assertValid(Type $parentType): void
    {
        $error = Utils::isValidNameError($this->name);
        if ($error !== null) {
            throw new InvariantViolation("{$parentType->name}.{$this->name}: {$error->getMessage()}");
        }

        $type = Type::getNamedType($this->getType());

        if (! $type instanceof InputType) {
            $notInputType = Utils::printSafe($this->type);

            throw new InvariantViolation("{$parentType->name}.{$this->name} field type must be Input Type but got: {$notInputType}");
        }

        if (array_key_exists('resolve', $this->config)) {
            throw new InvariantViolation("{$parentType->name}.{$this->name} field has a resolve property, but Input Types cannot define resolvers.");
        }
    }
}
