<?php
namespace GraphQL;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;

class Schema
{
    protected $querySchema;

    protected $mutationSchema;

    protected $_typeMap;

    protected $_directives;

    public function __construct(Type $querySchema = null, Type $mutationSchema = null)
    {
        Utils::invariant($querySchema || $mutationSchema, "Either query or mutation type must be set");
        $this->querySchema = $querySchema;
        $this->mutationSchema = $mutationSchema;
    }

    public function getQueryType()
    {
        return $this->querySchema;
    }

    public function getMutationType()
    {
        return $this->mutationSchema;
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
                Directive::ifDirective(),
                Directive::unlessDirective()
            ];
        }
        return $this->_directives;
    }

    public function getTypeMap()
    {
        if (null === $this->_typeMap) {
            $map = [];
            foreach ([$this->getQueryType(), $this->getMutationType(), Introspection::_schema()] as $type) {
                $this->_extractTypes($type, $map);
            }
            $this->_typeMap = $map + Type::getInternalTypes();
        }
        return $this->_typeMap;
    }

    private function _extractTypes($type, &$map)
    {
        if ($type instanceof WrappingType) {
            return $this->_extractTypes($type->getWrappedType(), $map);
        }

        if (!$type instanceof Type || !empty($map[$type->name])) {
            // TODO: warning?
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
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            foreach ((array) $type->getFields() as $fieldName => $field) {
                if (null === $field->args) {
                    trigger_error('WTF  ' . $field->name . ' has no args?'); // gg
                }
                $fieldArgTypes = array_map(function($arg) { return $arg->getType(); }, $field->args);
                $nestedTypes = array_merge($nestedTypes, $fieldArgTypes);
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
