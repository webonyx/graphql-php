<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Utils\Utils;

use function is_array;
use function is_callable;
use function is_iterable;
use function is_string;

/**
 * @phpstan-import-type UnnamedInputObjectFieldConfig from InputObjectField
 * @phpstan-type EagerFieldConfig InputObjectField|(Type&InputType)|UnnamedInputObjectFieldConfig
 * @phpstan-type LazyFieldConfig callable(): EagerFieldConfig
 * @phpstan-type FieldConfig EagerFieldConfig|LazyFieldConfig
 * @phpstan-type InputObjectConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   fields: iterable<FieldConfig>|callable(): iterable<FieldConfig>,
 *   astNode?: InputObjectTypeDefinitionNode|null,
 *   extensionASTNodes?: array<int, InputObjectTypeExtensionNode>|null,
 * }
 */
class InputObjectType extends Type implements InputType, NullableType, NamedType
{
    use NamedTypeImplementation;

    public ?InputObjectTypeDefinitionNode $astNode;

    /** @var array<int, InputObjectTypeExtensionNode> */
    public array $extensionASTNodes;

    /** @phpstan-var InputObjectConfig */
    public array $config;

    /**
     * Lazily initialized.
     *
     * @var array<string, InputObjectField>
     */
    private array $fields;

    /**
     * @phpstan-param InputObjectConfig $config
     */
    public function __construct(array $config)
    {
        $config['name'] ??= $this->tryInferName();
        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name              = $config['name'];
        $this->description       = $config['description'] ?? null;
        $this->astNode           = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /**
     * @throws InvariantViolation
     */
    public function getField(string $name): InputObjectField
    {
        $field = $this->findField($name);

        Utils::invariant($field !== null, 'Field "%s" is not defined for type "%s"', $name, $this->name);

        return $field;
    }

    public function findField(string $name): ?InputObjectField
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return $this->fields[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return isset($this->fields[$name]);
    }

    /**
     * @return array<string, InputObjectField>
     */
    public function getFields(): array
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return $this->fields;
    }

    protected function initializeFields(): void
    {
        $fields = $this->config['fields'];
        if (is_callable($fields)) {
            $fields = $fields();
        }

        $this->fields = [];
        foreach ($fields as $nameOrIndex => $field) {
            $this->initializeField($nameOrIndex, $field);
        }
    }

    /**
     * @param string|int $nameOrIndex
     * @phpstan-param FieldConfig $field
     */
    protected function initializeField($nameOrIndex, $field): void
    {
        if (is_callable($field)) {
            $field = $field();
        }

        if ($field instanceof Type) {
            $field = ['type' => $field];
        }

        if (is_array($field)) {
            $name = $field['name'] ??= $nameOrIndex;

            if (! is_string($name)) {
                throw new InvariantViolation(
                    "{$this->name} fields must be an associative array with field names as keys, an array of arrays with a name attribute, or a callable which returns one of those."
                );
            }

            $field = new InputObjectField($field);
        }

        $this->fields[$field->name] = $field;
    }

    /**
     * Validates type config and throws if one of type options is invalid.
     * Note: this method is shallow, it won't validate object fields and their arguments.
     *
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        $fields = $this->config['fields'] ?? null;
        if (is_callable($fields)) {
            $fields = $fields();
        }

        // @phpstan-ignore-next-line should not happen if used correctly
        if (! is_iterable($fields)) {
            $invalidFields = Utils::printSafe($fields);

            throw new InvariantViolation(
                "{$this->name} fields must be an iterable or a callable which returns an iterable, got: {$invalidFields}."
            );
        }

        $resolvedFields = $this->getFields();

        foreach ($resolvedFields as $field) {
            $field->assertValid($this);
        }
    }
}
