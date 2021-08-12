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
        if (! isset($this->fields)) {
            $this->initializeFields();
        }

        Utils::invariant(isset($this->fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);

        $this->initializeField($name);

        return $this->fields[$name];
    }

    /**
     * @return array<string, InputObjectField>
     */
    public function getFields(): array
    {
        if (! isset($this->fields)) {
            $this->initializeFields();
            foreach ($this->fields as $name => $_) {
                $this->initializeField($name);
            }
        }

        return $this->fields;
    }

    protected function initializeFields(): void
    {
        $fields = $this->config['fields'] ?? [];
        if (is_callable($fields)) {
            $fields = $fields();
        }

        if (! is_array($fields)) {
            throw new InvariantViolation(
                sprintf('%s fields must be an array or a callable which returns such an array.', $this->name)
            );
        }

        $this->fields = $fields;
    }

    protected function initializeField(string $name): void
    {
        $field = $this->fields[$name];
        if ($field instanceof InputObjectField) {
            return;
        }

        if (is_callable($field)) {
            $field = $field();
        }

        if ($field instanceof Type || is_callable($field)) {
            $field = ['type' => $field];
        }

        $field               = new InputObjectField($field + ['name' => $name]);
        $this->fields[$name] = $field;
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
