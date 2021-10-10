<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Utils\MixedStore;
use GraphQL\Utils\Utils;

use function is_array;
use function is_int;
use function is_string;
use function sprintf;

class EnumType extends Type implements InputType, OutputType, LeafType, NullableType, NamedType
{
    /** @var EnumTypeDefinitionNode|null */
    public ?TypeDefinitionNode $astNode;

    /**
     * Lazily initialized.
     *
     * @var array<int, EnumValueDefinition>
     */
    private array $values;

    /**
     * Lazily initialized.
     *
     * Actually a MixedStore<mixed, EnumValueDefinition>, PHPStan won't let us type it that way.
     */
    private MixedStore $valueLookup;

    /** @var array<string, EnumValueDefinition> */
    private array $nameLookup;

    /** @var array<int, EnumTypeExtensionNode> */
    public array $extensionASTNodes;

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

    public function getValue(string $name): ?EnumValueDefinition
    {
        if (! isset($this->nameLookup)) {
            $this->initializeNameLookup();
        }

        return $this->nameLookup[$name] ?? null;
    }

    /**
     * @return array<int, EnumValueDefinition>
     */
    public function getValues(): array
    {
        if (! isset($this->values)) {
            $this->values = [];
            $config       = $this->config;

            if (isset($config['values'])) {
                if (! is_array($config['values'])) {
                    throw new InvariantViolation(sprintf('%s values must be an array', $this->name));
                }

                foreach ($config['values'] as $name => $value) {
                    if (is_string($name)) {
                        if (is_array($value)) {
                            $value += ['name' => $name, 'value' => $name];
                        } else {
                            $value = ['name' => $name, 'value' => $value];
                        }
                    } elseif (is_int($name) && is_string($value)) {
                        $value = ['name' => $value, 'value' => $value];
                    } else {
                        throw new InvariantViolation(
                            sprintf(
                                '%s values must be an array with value names as keys.',
                                $this->name
                            )
                        );
                    }

                    $this->values[] = new EnumValueDefinition($value);
                }
            }
        }

        return $this->values;
    }

    public function serialize($value)
    {
        $lookup = $this->getValueLookup();
        if (isset($lookup[$value])) {
            return $lookup[$value]->name;
        }

        throw new SerializationError('Cannot serialize value as enum: ' . Utils::printSafe($value));
    }

    /**
     * Actually returns a MixedStore<mixed, EnumValueDefinition>, PHPStan won't let us type it that way
     */
    private function getValueLookup(): MixedStore
    {
        if (! isset($this->valueLookup)) {
            $this->valueLookup = new MixedStore();

            foreach ($this->getValues() as $valueName => $value) {
                $this->valueLookup->offsetSet($value->value, $value);
            }
        }

        return $this->valueLookup;
    }

    public function parseValue($value)
    {
        if (! isset($this->nameLookup)) {
            $this->initializeNameLookup();
        }

        if (isset($this->nameLookup[$value])) {
            return $this->nameLookup[$value]->value;
        }

        throw new Error('Cannot represent value as enum: ' . Utils::printSafe($value));
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof EnumValueNode) {
            if (! isset($this->nameLookup)) {
                $this->initializeNameLookup();
            }

            $name = $valueNode->value;
            if (isset($this->nameLookup[$name])) {
                return $this->nameLookup[$name]->value;
            }
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new Error();
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        parent::assertValid();

        Utils::invariant(
            isset($this->config['values']),
            sprintf('%s values must be an array.', $this->name)
        );

        $values = $this->getValues();
        foreach ($values as $value) {
            Utils::invariant(
                ! isset($value->config['isDeprecated']),
                sprintf(
                    '%s.%s should provide "deprecationReason" instead of "isDeprecated".',
                    $this->name,
                    $value->name
                )
            );
        }
    }

    private function initializeNameLookup(): void
    {
        $this->nameLookup = [];
        foreach ($this->getValues() as $value) {
            $this->nameLookup[$value->name] = $value;
        }
    }
}
