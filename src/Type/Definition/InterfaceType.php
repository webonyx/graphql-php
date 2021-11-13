<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Utils\Utils;

use function is_callable;
use function is_string;
use function sprintf;

class InterfaceType extends TypeWithFields implements AbstractType, OutputType, CompositeType, NullableType, NamedType, ImplementingType
{
    use TypeWithInterfaces;

    /** @var InterfaceTypeDefinitionNode|null */
    public ?TypeDefinitionNode $astNode;

    /** @var array<int, InterfaceTypeExtensionNode> */
    public array $extensionASTNodes;

    /**
     * @param array<string, mixed> $config
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
        parent::assertValid();

        $resolveType = $this->config['resolveType'] ?? null;

        Utils::invariant(
            ! isset($resolveType) || is_callable($resolveType),
            sprintf(
                '%s must provide "resolveType" as a function, but got: %s',
                $this->name,
                Utils::printSafe($resolveType)
            )
        );
    }
}
