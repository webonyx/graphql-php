<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * @phpstan-import-type UnnamedInputObjectFieldConfig from InputObjectField
 *
 * @phpstan-type EagerFieldConfig InputObjectField|(Type&InputType)|UnnamedInputObjectFieldConfig
 * @phpstan-type LazyFieldConfig callable(): EagerFieldConfig
 * @phpstan-type FieldConfig EagerFieldConfig|LazyFieldConfig
 * @phpstan-type InputObjectConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   fields: iterable<FieldConfig>|callable(): iterable<FieldConfig>,
 *   parseValue?: callable(array<string, mixed>): mixed,
 *   astNode?: InputObjectTypeDefinitionNode|null,
 *   extensionASTNodes?: array<int, InputObjectTypeExtensionNode>|null
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
     * @throws InvariantViolation
     *
     * @phpstan-param InputObjectConfig $config
     *
     * @throws InvariantViolation
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? $this->inferName();
        $this->description = $config['description'] ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /** @throws InvariantViolation */
    public function getField(string $name): InputObjectField
    {
        $field = $this->findField($name);

        if ($field === null) {
            throw new InvariantViolation("Field \"{$name}\" is not defined for type \"{$this->name}\"");
        }

        return $field;
    }

    /** @throws InvariantViolation */
    public function findField(string $name): ?InputObjectField
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return $this->fields[$name] ?? null;
    }

    /** @throws InvariantViolation */
    public function hasField(string $name): bool
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return isset($this->fields[$name]);
    }

    /**
     * @throws InvariantViolation
     *
     * @return array<string, InputObjectField>
     */
    public function getFields(): array
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        return $this->fields;
    }

    /** @throws InvariantViolation */
    protected function initializeFields(): void
    {
        $fields = $this->config['fields'];
        if (\is_callable($fields)) {
            $fields = $fields();
        }

        $this->fields = [];
        foreach ($fields as $nameOrIndex => $field) {
            $this->initializeField($nameOrIndex, $field);
        }
    }

    /**
     * @param string|int $nameOrIndex
     *
     * @phpstan-param FieldConfig $field
     *
     * @throws InvariantViolation
     */
    protected function initializeField($nameOrIndex, $field): void
    {
        if (\is_callable($field)) {
            $field = $field();
        }
        assert($field instanceof Type || is_array($field) || $field instanceof InputObjectField);

        if ($field instanceof Type) {
            $field = ['type' => $field];
        }
        assert(is_array($field) || $field instanceof InputObjectField); // @phpstan-ignore-line TODO remove when using actual union types

        if (\is_array($field)) {
            $field['name'] ??= $nameOrIndex;

            if (! \is_string($field['name'])) {
                throw new InvariantViolation("{$this->name} fields must be an associative array with field names as keys, an array of arrays with a name attribute, or a callable which returns one of those.");
            }

            $field = new InputObjectField($field); // @phpstan-ignore-line array type is wrongly inferred
        }
        assert($field instanceof InputObjectField); // @phpstan-ignore-line TODO remove when using actual union types

        $this->fields[$field->name] = $field;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid values, @see ClientAware.
     *
     * @param array<string, mixed> $value
     *
     * @return mixed
     */
    public function parseValue(array $value)
    {
        if (isset($this->config['parseValue'])) {
            return $this->config['parseValue']($value);
        }

        return $value;
    }

    /**
     * Validates type config and throws if one of type options is invalid.
     * Note: this method is shallow, it won't validate object fields and their arguments.
     *
     * @throws Error
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        $fields = $this->config['fields'] ?? null;
        if (\is_callable($fields)) {
            $fields = $fields();
        }

        if (! \is_iterable($fields)) {
            $invalidFields = Utils::printSafe($fields);
            throw new InvariantViolation("{$this->name} fields must be an iterable or a callable which returns an iterable, got: {$invalidFields}.");
        }

        $resolvedFields = $this->getFields();

        foreach ($resolvedFields as $field) {
            $field->assertValid($this);
        }
    }

    public function astNode(): ?InputObjectTypeDefinitionNode
    {
        return $this->astNode;
    }

    /** @return array<int, InputObjectTypeExtensionNode> */
    public function extensionASTNodes(): array
    {
        return $this->extensionASTNodes;
    }
}
