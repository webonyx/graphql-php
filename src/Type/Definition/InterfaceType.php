<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Utils\Utils;
use function is_callable;

/**
 * @phpstan-import-type ResolveType from AbstractType
 * @phpstan-import-type FieldsConfig from FieldDefinition
 * @phpstan-type InterfaceTypeReference InterfaceType|callable(): InterfaceType
 * @phpstan-type InterfaceConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   fields: FieldsConfig,
 *   interfaces?: iterable<InterfaceTypeReference>|callable(): iterable<InterfaceTypeReference>,
 *   resolveType?: ResolveType|null,
 *   astNode?: InterfaceTypeDefinitionNode|null,
 *   extensionASTNodes?: array<int, InterfaceTypeExtensionNode>|null,
 * }
 */
class InterfaceType extends Type implements AbstractType, OutputType, CompositeType, NullableType, HasFieldsType, NamedType, ImplementingType
{
    use HasFieldsTypeImplementation;
    use NamedTypeImplementation;
    use ImplementingTypeImplementation;

    public ?InterfaceTypeDefinitionNode $astNode;

    /** @var array<int, InterfaceTypeExtensionNode> */
    public array $extensionASTNodes;

    /** @phpstan-var InterfaceConfig */
    public array $config;

    /**
     * @phpstan-param InterfaceConfig $config
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? $this->inferName();
        $this->description = $config['description'] ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    /**
     * @param mixed $type
     *
     * @throws InvariantViolation
     */
    public static function assertInterfaceType($type): self
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Interface type.'
        );

        return $type;
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

        $this->assertValidInterfaces();
    }
}
