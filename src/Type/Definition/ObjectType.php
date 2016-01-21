<?php
namespace GraphQL\Type\Definition;
use GraphQL\Utils;


/**
 * Object Type Definition
 *
 * Almost all of the GraphQL types you define will be object types. Object types
 * have a name, but most importantly describe their fields.
 *
 * Example:
 *
 *     var AddressType = new GraphQLObjectType({
 *       name: 'Address',
 *       fields: {
 *         street: { type: GraphQLString },
 *         number: { type: GraphQLInt },
 *         formatted: {
 *           type: GraphQLString,
 *           resolve(obj) {
 *             return obj.number + ' ' + obj.street
 *           }
 *         }
 *       }
 *     });
 *
 * When two types need to refer to each other, or a type needs to refer to
 * itself in a field, you can use a function expression (aka a closure or a
 * thunk) to supply the fields lazily.
 *
 * Example:
 *
 *     var PersonType = new GraphQLObjectType({
 *       name: 'Person',
 *       fields: () => ({
 *         name: { type: GraphQLString },
 *         bestFriend: { type: PersonType },
 *       })
 *     });
 *
 */
class ObjectType extends Type implements OutputType, CompositeType
{
    /**
     * @var array<Field>
     */
    private $_fields;

    /**
     * @var array<InterfaceType>
     */
    private $_interfaces;

    /**
     * @var callable
     */
    private $_isTypeOf;

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

    public function __construct(array $config)
    {
        Utils::invariant(!empty($config['name']), 'Every type is expected to have name');

        // Note: this validation is disabled by default, because it is resource-consuming
        // TODO: add bin/validate script to check if schema is valid during development
        Config::validate($config, [
            'name' => Config::STRING | Config::REQUIRED,
            'fields' => Config::arrayOf(
                FieldDefinition::getDefinition(),
                Config::KEY_AS_NAME
            ),
            'description' => Config::STRING,
            'interfaces' => Config::arrayOf(
                Config::INTERFACE_TYPE
            ),
            'isTypeOf' => Config::CALLBACK, // ($value, ResolveInfo $info) => boolean
            'resolveField' => Config::CALLBACK
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->resolveFieldFn = isset($config['resolveField']) ? $config['resolveField'] : null;
        $this->_isTypeOf = isset($config['isTypeOf']) ? $config['isTypeOf'] : null;
        $this->config = $config;

        if (isset($config['interfaces'])) {
            InterfaceType::addImplementationToInterfaces($this);
        }
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields()
    {
        if (null === $this->_fields) {
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $fields = is_callable($fields) ? call_user_func($fields) : $fields;
            $this->_fields = FieldDefinition::createMap($fields);
        }
        return $this->_fields;
    }

    /**
     * @param string $name
     * @return FieldDefinition
     * @throws \Exception
     */
    public function getField($name)
    {
        if (null === $this->_fields) {
            $this->getFields();
        }
        Utils::invariant(isset($this->_fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);
        return $this->_fields[$name];
    }

    /**
     * @return array<InterfaceType>
     */
    public function getInterfaces()
    {
        if (null === $this->_interfaces) {
            $interfaces = isset($this->config['interfaces']) ? $this->config['interfaces'] : [];
            $interfaces = is_callable($interfaces) ? call_user_func($interfaces) : $interfaces;
            $this->_interfaces = $interfaces;
        }
        return $this->_interfaces;
    }

    /**
     * @param InterfaceType $iface
     * @return bool
     */
    public function implementsInterface(InterfaceType $iface)
    {
        return !!Utils::find($this->getInterfaces(), function($implemented) use ($iface) {return $iface === $implemented;});
    }

    /**
     * @param $value
     * @return bool|null
     */
    public function isTypeOf($value, ResolveInfo $info)
    {
        return isset($this->_isTypeOf) ? call_user_func($this->_isTypeOf, $value, $info) : null;
    }
}
