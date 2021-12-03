<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function array_key_exists;
use function is_array;

/**
 * @phpstan-type FieldArgumentConfig array{
 *     name: string,
 *     defaultValue?: mixed,
 *     description?: string|null,
 *     astNode?: InputValueDefinitionNode|null,
 * }
 */
class FieldArgument
{
    public string $name;

    /** @var mixed */
    public $defaultValue;

    public ?string $description;

    public ?InputValueDefinitionNode $astNode;

    /** @var FieldArgumentConfig */
    public array $config;

    /** @var Type&InputType */
    private Type $type;

    /**
     * @param FieldArgumentConfig $config
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
     * @param array<string, FieldArgumentConfig|Type> $config
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

    /**
     * @return Type&InputType
     */
    public function getType(): Type
    {
        if (! isset($this->type)) {
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
     */
    public function assertValid(FieldDefinition $parentField, Type $parentType): void
    {
        $error = Utils::isValidNameError($this->name);
        if ($error !== null) {
            throw new InvariantViolation(
                "{$parentType->name}.{$parentField->name}({$this->name}:) {$error->getMessage()}"
            );
        }

        $type = Type::getNamedType($this->getType());

        if (! $type instanceof InputType) {
            $notInputType = Utils::printSafe($this->type);

            throw new InvariantViolation(
                "{$parentType->name}.{$parentField->name}({$this->name}): argument type must be Input Type but got: {$notInputType}"
            );
        }
    }
}
