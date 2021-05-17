<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function array_map;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

class InterfaceType extends TypeWithFields implements AbstractType, OutputType, CompositeType, NullableType, NamedType, ImplementingType
{
    /** @var InterfaceTypeDefinitionNode|null */
    public $astNode;

    /** @var array<int, InterfaceTypeExtensionNode> */
    public $extensionASTNodes;

    /**
     * Lazily initialized.
     *
     * @var array<int, InterfaceType>
     */
    private $interfaces;

    /**
     * Lazily initialized.
     *
     * @var array<string, InterfaceType>
     */
    private $interfaceMap;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config)
    {
        if (! isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name              = $config['name'];
        $this->description       = $config['description'] ?? null;
        $this->astNode           = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? null;
        $this->config            = $config;
    }

    /**
     * @param mixed $type
     *
     * @return $this
     *
     * @throws InvariantViolation
     */
    public static function assertInterfaceType($type) : self
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Interface type.'
        );

        return $type;
    }

    public function implementsInterface(InterfaceType $interfaceType) : bool
    {
        if (! isset($this->interfaceMap)) {
            $this->interfaceMap = [];
            foreach ($this->getInterfaces() as $interface) {
                /** @var Type&InterfaceType $interface */
                $interface                            = Schema::resolveType($interface);
                $this->interfaceMap[$interface->name] = $interface;
            }
        }

        return isset($this->interfaceMap[$interfaceType->name]);
    }

    /**
     * @return array<int, InterfaceType>
     */
    public function getInterfaces() : array
    {
        if (! isset($this->interfaces)) {
            $interfaces = $this->config['interfaces'] ?? [];
            if (is_callable($interfaces)) {
                $interfaces = $interfaces();
            }

            if ($interfaces !== null && ! is_array($interfaces)) {
                throw new InvariantViolation(
                    sprintf('%s interfaces must be an Array or a callable which returns an Array.', $this->name)
                );
            }

            /** @var array<int, InterfaceType> $interfaces */
            $interfaces = $interfaces === null
                ? []
                : array_map([Schema::class, 'resolveType'], $interfaces);

            $this->interfaces = $interfaces;
        }

        return $this->interfaces;
    }

    /**
     * Resolves concrete ObjectType for given object value
     *
     * @param object $objectValue
     * @param mixed  $context
     *
     * @return Type|null
     */
    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        if (isset($this->config['resolveType'])) {
            $fn = $this->config['resolveType'];

            return $fn($objectValue, $context, $info);
        }

        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid() : void
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
