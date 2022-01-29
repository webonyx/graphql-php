<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function is_callable;
use function is_iterable;
use function is_string;

/**
 * @phpstan-import-type ResolveType from AbstractType
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
     * @phpstan-param UnionConfig $config
     */
    public function __construct(array $config)
    {
        $config['name'] ??= $this->tryInferName();
        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name = $config['name'];
        $this->description = $config['description'] ?? $this->description ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
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

            // @phpstan-ignore-next-line should not happen if used correctly
            if (! is_iterable($types)) {
                throw new InvariantViolation(
                    "Must provide iterable of types or a callable which returns such an iterable for Union {$this->name}."
                );
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

    /**
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        if (isset($this->config['resolveType']) && ! is_callable($this->config['resolveType'])) {
            $notCallable = Utils::printSafe($this->config['resolveType']);

            throw new InvariantViolation("{$this->name} must provide \"resolveType\" as a callable, but got: {$notCallable}");
        }
    }
}
