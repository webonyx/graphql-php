<?php
namespace GraphQL\Type\Definition;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Utils;


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
class ObjectType extends Type implements OutputType, CompositeType
{
    /**
     * @var FieldDefinition[]
     */
    private $fields;

    /**
     * @var InterfaceType[]
     */
    private $interfaces;

    /**
     * Keeping reference of config for late bindings and custom app-level metadata
     *
     * @var array
     */
    public $config;

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

        Utils::invariant(!empty($config['name']), 'Every type is expected to have name');

        // Note: this validation is disabled by default, because it is resource-consuming
        // TODO: add bin/validate script to check if schema is valid during development
        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'fields' => Config::arrayOf(
                FieldDefinition::getDefinition(),
                Config::KEY_AS_NAME | Config::MAYBE_THUNK | Config::MAYBE_TYPE
            ),
            'description' => Config::STRING,
            'interfaces' => Config::arrayOf(
                Config::INTERFACE_TYPE,
                Config::MAYBE_THUNK
            ),
            'isTypeOf' => Config::CALLBACK, // ($value, $context, ResolveInfo $info) => boolean
            'resolveField' => Config::CALLBACK // ($value, $args, $context, ResolveInfo $info) => $fieldValue
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->resolveFieldFn = isset($config['resolveField']) ? $config['resolveField'] : null;
        $this->config = $config;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields()
    {
        if (null === $this->fields) {
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $fields = is_callable($fields) ? call_user_func($fields) : $fields;
            $this->fields = FieldDefinition::createMap($fields, $this->name);
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
        if (!isset($this->fields[$name])) {
            throw new UserError(sprintf("Field '%s' is not defined for type '%s'", $name, $this->name));
        }
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

            $this->interfaces = [];
            foreach ($interfaces as $iface) {
                $iface = Type::resolve($iface);
                if (!$iface instanceof InterfaceType) {
                    throw new InvariantViolation(sprintf('Expecting interface type, got %s', Utils::printSafe($iface)));
                }
                $this->interfaces[] = $iface;
            }
        }
        return $this->interfaces;
    }

    /**
     * @param InterfaceType $iface
     * @return bool
     */
    public function implementsInterface($iface)
    {
        $iface = Type::resolve($iface);
        return !!Utils::find($this->getInterfaces(), function($implemented) use ($iface) {return $iface === $implemented;});
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
}
