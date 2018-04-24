<?php
namespace GraphQL\Type;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;

/**
 * EXPERIMENTAL!
 * This class can be removed or changed in future versions without a prior notice.
 *
 * Class EagerResolution
 * @package GraphQL\Type
 */
class EagerResolution implements Resolution
{
    /**
     * @var Type[]
     */
    private $typeMap = [];

    /**
     * @var array<string, ObjectType[]>
     */
    private $implementations = [];

    /**
     * EagerResolution constructor.
     * @param Type[] $initialTypes
     */
    public function __construct(array $initialTypes)
    {
        $typeMap = [];
        foreach ($initialTypes as $type) {
            $typeMap = TypeInfo::extractTypes($type, $typeMap);
        }
        $this->typeMap = $typeMap + Type::getInternalTypes();

        // Keep track of all possible types for abstract types
        foreach ($this->typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->implementations[$iface->name][] = $type;
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function resolveType($name)
    {
        return isset($this->typeMap[$name]) ? $this->typeMap[$name] : null;
    }

    /**
     * @inheritdoc
     */
    public function resolvePossibleTypes(AbstractType $abstractType)
    {
        if (!isset($this->typeMap[$abstractType->name])) {
            return [];
        }

        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }

        /** @var InterfaceType $abstractType */
        Utils::invariant($abstractType instanceof InterfaceType);
        return isset($this->implementations[$abstractType->name]) ? $this->implementations[$abstractType->name] : [];
    }

    /**
     * @return Type[]
     */
    public function getTypeMap()
    {
        return $this->typeMap;
    }

    /**
     * Returns serializable schema representation suitable for GraphQL\Type\LazyResolution
     *
     * @return array
     */
    public function getDescriptor()
    {
        $typeMap = [];
        $possibleTypesMap = [];
        foreach ($this->getTypeMap() as $type) {
            if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $innerType) {
                    $possibleTypesMap[$type->name][$innerType->name] = 1;
                }
            } else if ($type instanceof InterfaceType) {
                foreach ($this->implementations[$type->name] as $obj) {
                    $possibleTypesMap[$type->name][$obj->name] = 1;
                }
            }
            $typeMap[$type->name] = 1;
        }
        return [
            'version' => '1.0',
            'typeMap' => $typeMap,
            'possibleTypeMap' => $possibleTypesMap
        ];
    }
}
