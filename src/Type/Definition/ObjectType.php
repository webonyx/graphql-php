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
     * Keeping reference of config for late bindings
     *
     * @var array
     */
    private $_config;

    private $_initialized = false;

    /**
     * @var callable
     */
    public $resolveField;

    public function __construct(array $config)
    {
        Utils::invariant(!empty($config['name']), 'Every type is expected to have name');

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->resolveField = isset($config['resolveField']) ? $config['resolveField'] : null;
        $this->_config = $config;

        if (isset($config['interfaces'])) {
            InterfaceType::addImplementationToInterfaces($this);
        }
    }

    /**
     * Late instance initialization
     */
    private function initialize()
    {
        if ($this->_initialized) {
            return ;
        }
        $config = $this->_config;

        if (isset($config['fields']) && is_callable($config['fields'])) {
            $config['fields'] = call_user_func($config['fields']);
        }
        if (isset($config['interfaces']) && is_callable($config['interfaces'])) {
            $config['interfaces'] = call_user_func($config['interfaces']);
        }

        // Note: this validation is disabled by default, because it is resource-consuming
        // TODO: add bin/validate script to check if schema is valid during development
        Config::validate($this->_config, [
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

        $this->_fields = FieldDefinition::createMap($config['fields']);
        $this->_interfaces = isset($config['interfaces']) ? $config['interfaces'] : [];
        $this->_isTypeOf = isset($config['isTypeOf']) ? $config['isTypeOf'] : null;
        $this->_initialized = true;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields()
    {
        if (false === $this->_initialized) {
            $this->initialize();
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
        if (false === $this->_initialized) {
            $this->initialize();
        }
        Utils::invariant(isset($this->_fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);
        return $this->_fields[$name];
    }

    /**
     * @return array<InterfaceType>
     */
    public function getInterfaces()
    {
        if (false === $this->_initialized) {
            $this->initialize();
        }
        return $this->_interfaces;
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
