<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Utils\Utils;

use function count;
use function is_array;
use function is_callable;
use function is_iterable;
use function is_string;
use function sprintf;

class InputObjectType extends Type implements InputType, NullableType, NamedType
{
    /** @var InputObjectTypeDefinitionNode|null */
    public ?TypeDefinitionNode $astNode;

    /**
     * Lazily initialized.
     *
     * @var array<string, InputObjectField>
     */
    private array $fields;

    /** @var array<int, InputObjectTypeExtensionNode> */
    public array $extensionASTNodes;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $config['name'] ??= $this->tryInferName();
        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name              = $config['name'];
        $this->astNode           = $config['astNode'] ?? null;
        $this->description       = $config['description'] ?? null;
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
        $fields = $this->config['fields'] ?? [];
        if (is_callable($fields)) {
            $fields = $fields();
        }

        if (! is_iterable($fields)) {
            throw new InvariantViolation(
                sprintf('%s fields must be an iterable or a callable which returns such an iterable.', $this->name)
            );
        }

        $this->fields = [];
        foreach ($fields as $nameOrIndex => $field) {
            $this->initializeField($nameOrIndex, $field);
        }
    }

    /**
     * @param string|int                                                            $nameOrIndex
     * @param InputObjectField|Type|array|(callable(): InputObjectField|Type|array) $field
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
        parent::assertValid();

        Utils::invariant(
            count($this->getFields()) > 0,
            sprintf(
                '%s fields must be an associative array with field names as keys or a callable which returns such an array.',
                $this->name
            )
        );

        foreach ($this->getFields() as $field) {
            $field->assertValid($this);
        }
    }
}
