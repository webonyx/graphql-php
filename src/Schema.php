<?php
namespace GraphQL;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;

class Schema
{
    /**
     * @var ObjectType
     */
    protected $_queryType;

    /**
     * @var ObjectType
     */
    protected $_mutationType;

    /**
     * @var ObjectType
     */
    protected $_subscriptionType;

    /**
     * @var Directive[]
     */
    protected $_directives;

    /**
     * @var array<string, Type>
     */
    protected $_typeMap;

    /**
     * @var array<string, ObjectType[]>
     */
    protected $_implementations;

    /**
     * @var array<string, array<string, boolean>>
     */
    protected $_possibleTypeMap;

    /**
     * Schema constructor.
     * @param array $config
     */
    public function __construct($config = null)
    {
        if (func_num_args() > 1 || $config instanceof Type) {
            trigger_error(
                'GraphQL\Schema constructor expects config object now instead of types passed as arguments. '.
                'See https://github.com/webonyx/graphql-php/issues/36',
                E_USER_DEPRECATED
            );
            list($queryType, $mutationType, $subscriptionType) = func_get_args();

            $config = [
                'query' => $queryType,
                'mutation' => $mutationType,
                'subscription' => $subscriptionType
            ];
        }

        $this->_init($config);
    }

    protected function _init(array $config)
    {
        Utils::invariant(isset($config['query']) || isset($config['mutation']), "Either query or mutation type must be set");

        $config += [
            'query' => null,
            'mutation' => null,
            'subscription' => null,
            'directives' => [],
            'validate' => true
        ];

        $this->_queryType = $config['query'];
        $this->_mutationType = $config['mutation'];
        $this->_subscriptionType = $config['subscription'];

        $this->_directives = array_merge($config['directives'], [
            Directive::includeDirective(),
            Directive::skipDirective()
        ]);

        // Build type map now to detect any errors within this schema.
        $initialTypes = [
            $config['query'],
            $config['mutation'],
            $config['subscription'],
            Introspection::_schema()
        ];
        if (!empty($config['types'])) {
            $initialTypes = array_merge($initialTypes, $config['types']);
        }

        $map = [];
        foreach ($initialTypes as $type) {
            $this->_extractTypes($type, $map);
        }
        $this->_typeMap = $map + Type::getInternalTypes();

        // Keep track of all implementations by interface name.
        $this->_implementations = [];
        foreach ($this->_typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->_implementations[$iface->name][] = $type;
                }
            }
        }

        if ($config['validate']) {
            $this->validate();
        }
    }

    /**
     * Additionaly validate schema for integrity
     */
    public function validate()
    {
        // Enforce correct interface implementations
        foreach ($this->_typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->_assertObjectImplementsInterface($type, $iface);
                }
            }
        }
    }

    /**
     * @param ObjectType $object
     * @param InterfaceType $iface
     * @throws \Exception
     */
    protected function _assertObjectImplementsInterface(ObjectType $object, InterfaceType $iface)
    {
        $objectFieldMap = $object->getFields();
        $ifaceFieldMap = $iface->getFields();

        foreach ($ifaceFieldMap as $fieldName => $ifaceField) {
            Utils::invariant(
                isset($objectFieldMap[$fieldName]),
                "\"$iface\" expects field \"$fieldName\" but \"$object\" does not provide it"
            );

            /** @var $ifaceField FieldDefinition */
            /** @var $objectField FieldDefinition */
            $objectField = $objectFieldMap[$fieldName];

            Utils::invariant(
                $this->_isEqualType($ifaceField->getType(), $objectField->getType()),
                "$iface.$fieldName expects type \"{$ifaceField->getType()}\" but " .
                "$object.$fieldName provides type \"{$objectField->getType()}"
            );

            foreach ($ifaceField->args as $ifaceArg) {
                /** @var $ifaceArg FieldArgument */
                /** @var $objectArg FieldArgument */
                $argName = $ifaceArg->name;
                $objectArg = $objectField->getArg($argName);

                // Assert interface field arg exists on object field.
                Utils::invariant(
                    $objectArg,
                    "$iface.$fieldName expects argument \"$argName\" but $object.$fieldName does not provide it."
                );

                // Assert interface field arg type matches object field arg type.
                // (invariant)
                Utils::invariant(
                    $this->_isEqualType($ifaceArg->getType(), $objectArg->getType()),
                    "$iface.$fieldName($argName:) expects type \"{$ifaceArg->getType()}\" " .
                    "but $object.$fieldName($argName:) provides " .
                    "type \"{$objectArg->getType()}\""
                );

                // Assert argument set invariance.
                foreach ($objectField->args as $objectArg) {
                    $argName = $objectArg->name;
                    $ifaceArg = $ifaceField->getArg($argName);
                    Utils::invariant(
                        $ifaceArg,
                        "$iface.$fieldName does not define argument \"$argName\" but " .
                        "$object.$fieldName provides it."
                    );
                }
            }
        }
    }

    /**
     * @param $typeA
     * @param $typeB
     * @return bool
     */
    protected function _isEqualType($typeA, $typeB)
    {
        if ($typeA instanceof NonNull && $typeB instanceof NonNull) {
            return $this->_isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }
        if ($typeA instanceof ListOfType && $typeB instanceof ListOfType) {
            return $this->_isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }
        return $typeA === $typeB;
    }

    /**
     * @return ObjectType
     */
    public function getQueryType()
    {
        return $this->_queryType;
    }

    /**
     * @return ObjectType
     */
    public function getMutationType()
    {
        return $this->_mutationType;
    }

    /**
     * @return ObjectType
     */
    public function getSubscriptionType()
    {
        return $this->_subscriptionType;
    }

    /**
     * @return array
     */
    public function getTypeMap()
    {
        return $this->_typeMap;
    }

    /**
     * @param string $name
     * @return Type
     */
    public function getType($name)
    {
        $map = $this->getTypeMap();
        return isset($map[$name]) ? $map[$name] : null;
    }

    /**
     * @param AbstractType $abstractType
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }
        Utils::invariant($abstractType instanceof InterfaceType);
        return $this->_implementations[$abstractType->name];
    }

    /**
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if (null === $this->_possibleTypeMap) {
            $this->_possibleTypeMap = [];
        }

        if (!isset($this->_possibleTypeMap[$abstractType->name])) {
            $tmp = [];
            foreach ($this->getPossibleTypes($abstractType) as $type) {
                $tmp[$type->name] = true;
            }
            $this->_possibleTypeMap[$abstractType->name] = $tmp;
        }

        return !empty($this->_possibleTypeMap[$abstractType->name][$possibleType->name]);
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->_directives;
    }

    /**
     * @param $name
     * @return Directive
     */
    public function getDirective($name)
    {
        foreach ($this->getDirectives() as $directive) {
            if ($directive->name === $name) {
                return $directive;
            }
        }
        return null;
    }

    protected function _extractTypes($type, &$map)
    {
        if (!$type) {
            return $map;
        }

        if ($type instanceof WrappingType) {
            return $this->_extractTypes($type->getWrappedType(), $map);
        }

        if (!empty($map[$type->name])) {
            Utils::invariant(
                $map[$type->name] === $type,
                "Schema must contain unique named types but contains multiple types named \"$type\"."
            );
            return $map;
        }
        $map[$type->name] = $type;

        $nestedTypes = [];

        if ($type instanceof UnionType) {
            $nestedTypes = $type->getTypes();
        }
        if ($type instanceof ObjectType) {
            $nestedTypes = array_merge($nestedTypes, $type->getInterfaces());
        }
        if ($type instanceof ObjectType || $type instanceof InterfaceType || $type instanceof InputObjectType) {
            foreach ((array) $type->getFields() as $fieldName => $field) {
                if (isset($field->args)) {
                    $fieldArgTypes = array_map(function($arg) { return $arg->getType(); }, $field->args);
                    $nestedTypes = array_merge($nestedTypes, $fieldArgTypes);
                }
                $nestedTypes[] = $field->getType();
            }
        }
        foreach ($nestedTypes as $type) {
            $this->_extractTypes($type, $map);
        }
        return $map;
    }
}
