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
    private $_fields = [];

    /**
     * @var array<InterfaceType>
     */
    private $_interfaces;

    /**
     * @var callable
     */
    private $_isTypeOf;

    public function __construct(array $config)
    {
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
            'isTypeOf' => Config::CALLBACK,
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;

        if (isset($config['fields'])) {
            $this->_fields = FieldDefinition::createMap($config['fields']);
        }

        $this->_interfaces = isset($config['interfaces']) ? $config['interfaces'] : [];
        $this->_isTypeOf = isset($config['isTypeOf']) ? $config['isTypeOf'] : null;

        if (!empty($this->_interfaces)) {
            InterfaceType::addImplementationToInterfaces($this, $this->_interfaces);
        }
    }

    /**
     * @return array<FieldDefinition>
     */
    public function getFields()
    {
        return $this->_fields;
    }

    /**
     * @param string $name
     * @return FieldDefinition
     * @throws \Exception
     */
    public function getField($name)
    {
        Utils::invariant(isset($this->_fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);
        return $this->_fields[$name];
    }

    /**
     * @return array<InterfaceType>
     */
    public function getInterfaces()
    {
        return $this->_interfaces;
    }

    /**
     * @param $value
     * @return bool|null
     */
    public function isTypeOf($value)
    {
        return isset($this->_isTypeOf) ? call_user_func($this->_isTypeOf, $value) : null;
    }
}
