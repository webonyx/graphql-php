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
        if (! isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->config            = $config;
        $this->name              = $config['name'];
        $this->astNode           = $config['astNode'] ?? null;
        $this->description       = $config['description'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];
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
        if (! $this->hasField($name)) {
            return null;
        }

        return $this->fields[$name];
    }

    public function hasField(string $name): bool
    {
        $this->initializeFields();

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
        if (isset($this->fields)) {
            return;
        }

        $fields = $this->config['fields'] ?? [];
        if (is_callable($fields)) {
            $fields = $fields();
        }

        if (! is_array($fields)) {
            throw new InvariantViolation(
                sprintf('%s fields must be an array or a callable which returns such an array.', $this->name)
            );
        }

        $this->fields = [];
        foreach ($fields as $name => $field) {
            $this->initializeField($name, $field);
        }
    }

    /**
     * @param int|string                          $name
     * @param Type|array|(callable(): Type|array) $field
     */
    protected function initializeField($name, $field): void
    {
        if (is_callable($field)) {
            $field = $field();
        }

        if ($field instanceof Type) {
            $field = ['type' => $field];
        }

        $name = $field['name'] ??= $name;

        if (! is_string($name)) {
            throw new InvariantViolation(
                "{$this->name} fields must be an associative array with field names as keys, an array of arrays with a name attribute, or a callable which returns one of those."
            );
        }

        $this->fields[$name] = new InputObjectField($field);
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
