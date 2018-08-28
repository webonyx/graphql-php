<?php

declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use function call_user_func;
use function sprintf;

/**
 * EXPERIMENTAL!
 * This class can be removed or changed in future versions without a prior notice.
 */
class LazyResolution implements Resolution
{
    /** @var int[] */
    private $typeMap;

    /** @var int[][] */
    private $possibleTypeMap;

    /** @var callable */
    private $typeLoader;

    /**
     * List of currently loaded types
     *
     * @var Type[]
     */
    private $loadedTypes;

    /**
     * Map of $interfaceTypeName => $objectType[]
     *
     * @var Type[][]
     */
    private $loadedPossibleTypes;

    /**
     *
     * @param mixed[] $descriptor
     */
    public function __construct(array $descriptor, callable $typeLoader)
    {
        Utils::invariant(
            isset($descriptor['typeMap'], $descriptor['possibleTypeMap'], $descriptor['version'])
        );
        Utils::invariant(
            $descriptor['version'] === '1.0'
        );

        $this->typeLoader          = $typeLoader;
        $this->typeMap             = $descriptor['typeMap'] + Type::getInternalTypes();
        $this->possibleTypeMap     = $descriptor['possibleTypeMap'];
        $this->loadedTypes         = Type::getInternalTypes();
        $this->loadedPossibleTypes = [];
    }

    /**
     * @inheritdoc
     */
    public function resolvePossibleTypes(AbstractType $type)
    {
        if (! isset($this->possibleTypeMap[$type->name])) {
            return [];
        }
        if (! isset($this->loadedPossibleTypes[$type->name])) {
            $tmp = [];
            foreach ($this->possibleTypeMap[$type->name] as $typeName => $true) {
                $obj = $this->resolveType($typeName);
                if (! $obj instanceof ObjectType) {
                    throw new InvariantViolation(
                        sprintf(
                            'Lazy Type Resolution Error: Implementation %s of interface %s is expected to be instance of ObjectType, but got %s',
                            $typeName,
                            $type->name,
                            Utils::getVariableType($obj)
                        )
                    );
                }
                $tmp[] = $obj;
            }
            $this->loadedPossibleTypes[$type->name] = $tmp;
        }

        return $this->loadedPossibleTypes[$type->name];
    }

    /**
     * @inheritdoc
     */
    public function resolveType($name)
    {
        if (! isset($this->typeMap[$name])) {
            return null;
        }
        if (! isset($this->loadedTypes[$name])) {
            $type = call_user_func($this->typeLoader, $name);
            if (! $type instanceof Type && $type !== null) {
                throw new InvariantViolation(
                    'Lazy Type Resolution Error: Expecting GraphQL Type instance, but got ' .
                    Utils::getVariableType($type)
                );
            }

            $this->loadedTypes[$name] = $type;
        }

        return $this->loadedTypes[$name];
    }
}
