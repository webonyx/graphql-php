<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

/**
 * @phpstan-import-type ResolveType from AbstractType
 *
 * @phpstan-type ObjectTypeReference ObjectType|callable(): ObjectType
 * @phpstan-type UnionConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   types: iterable<ObjectTypeReference>|callable(): iterable<ObjectTypeReference>,
 *   resolveType?: ResolveType|null,
 *   astNode?: UnionTypeDefinitionNode|null,
 *   extensionASTNodes?: array<UnionTypeExtensionNode>|null
 * }
 */
class UnionType extends Type implements AbstractType, OutputType, CompositeType, NullableType, NamedType
{
    use NamedTypeImplementation;

    public ?UnionTypeDefinitionNode $astNode;

    /** @var array<UnionTypeExtensionNode> */
    public array $extensionASTNodes;

    /** @phpstan-var UnionConfig */
    public array $config;

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

    /**
     * @throws InvariantViolation
     *
     * @phpstan-param UnionConfig $config
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? $this->inferName();
        $this->description = $config['description'] ?? $this->description ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /** @throws InvariantViolation */
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
     * @throws InvariantViolation
     *
     * @return array<int, ObjectType>
     */
    public function getTypes(): array
    {
        if (! isset($this->types)) {
            $this->types = [];

            $types = $this->config['types'] ?? null;
            if (is_callable($types)) {
                $types = $types();
            }

            if (! is_iterable($types)) {
                throw new InvariantViolation("Must provide iterable of types or a callable which returns such an iterable for Union {$this->name}.");
            }

            foreach ($types as $type) {
                $this->types[] = Schema::resolveType($type);
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

    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        $resolveType = $this->config['resolveType'] ?? null;
        // @phpstan-ignore-next-line not necessary according to types, but can happen during runtime
        if (isset($resolveType) && ! is_callable($resolveType)) {
            $notCallable = Utils::printSafe($resolveType);
            throw new InvariantViolation("{$this->name} must provide \"resolveType\" as null or a callable, but got: {$notCallable}.");
        }
    }

    public function astNode(): ?UnionTypeDefinitionNode
    {
        return $this->astNode;
    }

    /** @return array<UnionTypeExtensionNode> */
    public function extensionASTNodes(): array
    {
        return $this->extensionASTNodes;
    }
}
