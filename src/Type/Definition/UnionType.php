<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

class UnionType extends Type implements AbstractType, OutputType, CompositeType, NullableType, NamedType
{
    /** @var UnionTypeDefinitionNode|null */
    public ?TypeDefinitionNode $astNode;

    /**
     * Lazily initialized.
     *
     * @var array<int, ObjectType>
     */
    private array $types;

    /**
     * Lazily initialized.
     *
     * @var array<string, bool>
     */
    private array $possibleTypeNames;

    /** @var array<int, UnionTypeExtensionNode> */
    public array $extensionASTNodes;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config)
    {
        if (! isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        /**
         * Optionally provide a custom type resolver function. If one is not provided,
         * the default implementation will call `isTypeOf` on each implementing
         * Object type.
         */
        $this->name              = $config['name'];
        $this->description       = $config['description'] ?? null;
        $this->astNode           = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];
        $this->config            = $config;
    }

    public function isPossibleType(Type $type): bool
    {
        if (! $type instanceof ObjectType) {
            return false;
        }

        if (! isset($this->possibleTypeNames)) {
            $this->possibleTypeNames = [];
            foreach ($this->getTypes() as $possibleType) {
                $this->possibleTypeNames[$possibleType->name] = true;
            }
        }

        return isset($this->possibleTypeNames[$type->name]);
    }

    /**
     * @return array<int, ObjectType>
     *
     * @throws InvariantViolation
     */
    public function getTypes(): array
    {
        if (! isset($this->types)) {
            $this->types = [];

            $types = $this->config['types'] ?? null;
            if (is_callable($types)) {
                $types = $types();
            }

            if (! is_array($types)) {
                throw new InvariantViolation(
                    "Must provide Array of types or a callable which returns such an array for Union {$this->name}."
                );
            }

            foreach ($types as $type) {
                /**
                 * Might not be true, actually checked during schema validation.
                 *
                 * @var ObjectType $resolvedType
                 */
                $resolvedType  = Schema::resolveType($type);
                $this->types[] = $resolvedType;
            }
        }

        return $this->types;
    }

    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        if (isset($this->config['resolveType'])) {
            return ($this->config['resolveType'])($objectValue, $context, $info);
        }

        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        parent::assertValid();

        if (! isset($this->config['resolveType'])) {
            return;
        }

        Utils::invariant(
            is_callable($this->config['resolveType']),
            sprintf(
                '%s must provide "resolveType" as a function, but got: %s',
                $this->name,
                Utils::printSafe($this->config['resolveType'])
            )
        );
    }
}
