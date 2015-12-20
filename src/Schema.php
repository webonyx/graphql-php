<?php
namespace GraphQL;

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
    protected $querySchema;

    protected $mutationSchema;

    protected $subscriptionSchema;

    protected $_typeMap;

    protected $_directives;

    public function __construct(Type $querySchema = null, Type $mutationSchema = null, Type $subscriptionSchema = null)
    {
        Utils::invariant($querySchema || $mutationSchema, "Either query or mutation type must be set");
        $this->querySchema = $querySchema;
        $this->mutationSchema = $mutationSchema;
        $this->subscriptionSchema = $subscriptionSchema;

        // Build type map now to detect any errors within this schema.
        $map = [];
        foreach ([$this->getQueryType(), $this->getMutationType(), Introspection::_schema()] as $type) {
            $this->_extractTypes($type, $map);
        }
        $this->_typeMap = $map + Type::getInternalTypes();

        // Enforce correct interface implementations
        foreach ($this->_typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->assertObjectImplementsInterface($type, $iface);
                }
            }
        }
    }

    /**
     * @param ObjectType $object
     * @param InterfaceType $iface
     * @throws \Exception
     */
    private function assertObjectImplementsInterface(ObjectType $object, InterfaceType $iface)
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
                $this->isEqualType($ifaceField->getType(), $objectField->getType()),
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
                    $this->isEqualType($ifaceArg->getType(), $objectArg->getType()),
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
    private function isEqualType($typeA, $typeB)
    {
        if ($typeA instanceof NonNull && $typeB instanceof NonNull) {
            return $this->isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }
        if ($typeA instanceof ListOfType && $typeB instanceof ListOfType) {
            return $this->isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }
        return $typeA === $typeB;
    }

    public function getQueryType()
    {
        return $this->querySchema;
    }

    public function getMutationType()
    {
        return $this->mutationSchema;
    }

    public function getSubscriptionType()
    {
        return $this->subscriptionSchema;
    }

    /**
     * @param $name
     * @return null
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

    /**
     * @return array<Directive>
     */
    public function getDirectives()
    {
        if (!$this->_directives) {
            $this->_directives = [
                Directive::includeDirective(),
                Directive::skipDirective()
            ];
        }
        return $this->_directives;
    }

    public function getTypeMap()
    {
        return $this->_typeMap;
    }

    private function _extractTypes($type, &$map)
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

        if ($type instanceof InterfaceType || $type instanceof UnionType) {
            $nestedTypes = $type->getPossibleTypes();
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

    public function getType($name)
    {
        $map = $this->getTypeMap();
        return isset($map[$name]) ? $map[$name] : null;
    }
}
