<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Utils\Utils;


/**
 * Object Type Definition
 *
 * Almost all of the GraphQL types you define will be object types. Object types
 * have a name, but most importantly describe their fields.
 *
 * Example:
 *
 *     $AddressType = new ObjectType([
 *       'name' => 'Address',
 *       'fields' => [
 *         'street' => [ 'type' => GraphQL\Type\Definition\Type::string() ],
 *         'number' => [ 'type' => GraphQL\Type\Definition\Type::int() ],
 *         'formatted' => [
 *           'type' => GraphQL\Type\Definition\Type::string(),
 *           'resolve' => function($obj) {
 *             return $obj->number . ' ' . $obj->street;
 *           }
 *         ]
 *       ]
 *     ]);
 *
 * When two types need to refer to each other, or a type needs to refer to
 * itself in a field, you can use a function expression (aka a closure or a
 * thunk) to supply the fields lazily.
 *
 * Example:
 *
 *     $PersonType = null;
 *     $PersonType = new ObjectType([
 *       'name' => 'Person',
 *       'fields' => function() use (&$PersonType) {
 *          return [
 *              'name' => ['type' => GraphQL\Type\Definition\Type::string() ],
 *              'bestFriend' => [ 'type' => $PersonType ],
 *          ];
 *        }
 *     ]);
 *
 */
class ObjectType extends Type implements OutputType, CompositeType, NamedType
{
    /**
     * @param mixed $type
     * @return self
     */
    public static function assertObjectType($type)
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Object type.'
        );

        return $type;
    }

    /**
     * @var FieldDefinition[]
     */
    private $fields;

    /**
     * @var InterfaceType[]
     */
    private $interfaces;

    /**
     * @var array
     */
    private $interfaceMap;

    /**
     * @var ObjectTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * @var ObjectTypeExtensionNode[]
     */
    public $extensionASTNodes;

    /**
     * @var callable
     */
    public $resolveFieldFn;

    /**
     * ObjectType constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->resolveFieldFn = isset($config['resolveField']) ? $config['resolveField'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->extensionASTNodes = isset($config['extensionASTNodes']) ? $config['extensionASTNodes'] : [];
        $this->config = $config;
    }

    /**
     * @return FieldDefinition[]
     * @throws InvariantViolation
     */
    public function getFields()
    {
        if (null === $this->fields) {
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $this->fields = FieldDefinition::defineFieldMap($this, $fields);
        }
        return $this->fields;
    }

    /**
     * @param string $name
     * @return FieldDefinition
     * @throws \Exception
     */
    public function getField($name)
    {
        if (null === $this->fields) {
            $this->getFields();
        }
        Utils::invariant(isset($this->fields[$name]), 'Field "%s" is not defined for type "%s"', $name, $this->name);
        return $this->fields[$name];
    }

    /**
     * @return InterfaceType[]
     */
    public function getInterfaces()
    {
        if (null === $this->interfaces) {
            $interfaces = isset($this->config['interfaces']) ? $this->config['interfaces'] : [];
            $interfaces = is_callable($interfaces) ? call_user_func($interfaces) : $interfaces;

            if ($interfaces && !is_array($interfaces)) {
                throw new InvariantViolation(
                    "{$this->name} interfaces must be an Array or a callable which returns an Array."
                );
            }

            $this->interfaces = $interfaces ?: [];
        }
        return $this->interfaces;
    }

    private function getInterfaceMap()
    {
        if (!$this->interfaceMap) {
            $this->interfaceMap = [];
            foreach ($this->getInterfaces() as $interface) {
                $this->interfaceMap[$interface->name] = $interface;
            }
        }
        return $this->interfaceMap;
    }

    /**
     * @param InterfaceType $iface
     * @return bool
     */
    public function implementsInterface($iface)
    {
        $map = $this->getInterfaceMap();
        return isset($map[$iface->name]);
    }

    /**
     * @param $value
     * @param $context
     * @param ResolveInfo $info
     * @return bool|null
     */
    public function isTypeOf($value, $context, ResolveInfo $info)
    {
        return isset($this->config['isTypeOf']) ? call_user_func($this->config['isTypeOf'], $value, $context, $info) : null;
    }

    /**
     * Validates type config and throws if one of type options is invalid.
     * Note: this method is shallow, it won't validate object fields and their arguments.
     *
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        parent::assertValid();

        Utils::invariant(
            null === $this->description || is_string($this->description),
            "{$this->name} description must be string if set, but it is: " . Utils::printSafe($this->description)
        );

        Utils::invariant(
            !isset($this->config['isTypeOf']) || is_callable($this->config['isTypeOf']),
            "{$this->name} must provide 'isTypeOf' as a function"
        );
    }
}
