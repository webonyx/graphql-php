<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class InterfaceType
 * @package GraphQL\Type\Definition
 */
class InterfaceType extends Type implements AbstractType, OutputType, CompositeType
{
    /**
     * @var FieldDefinition[]
     */
    private $fields;

    /**
     * @var InterfaceTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * InterfaceType constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::assertValidName($config['name']);

        Config::validate($config, [
            'name' => Config::NAME,
            'fields' => Config::arrayOf(
                FieldDefinition::getDefinition(),
                Config::KEY_AS_NAME | Config::MAYBE_THUNK | Config::MAYBE_TYPE
            ),
            'resolveType' => Config::CALLBACK, // function($value, $context, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->config = $config;
    }

    /**
     * @return FieldDefinition[]
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
     * @param $name
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
     * Resolves concrete ObjectType for given object value
     *
     * @param $objectValue
     * @param $context
     * @param ResolveInfo $info
     * @return callable|null
     */
    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        if (isset($this->config['resolveType'])) {
            $fn = $this->config['resolveType'];
            return $fn($objectValue, $context, $info);
        }
        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        parent::assertValid();

        $fields = $this->getFields();

        Utils::invariant(
            !isset($this->config['resolveType']) || is_callable($this->config['resolveType']),
            "{$this->name} must provide \"resolveType\" as a function."
        );

        Utils::invariant(
            !empty($fields),
            "{$this->name} fields must not be empty"
        );

        foreach ($fields as $field) {
            $field->assertValid($this);
            foreach ($field->args as $arg) {
                $arg->assertValid($field, $this);
            }
        }
    }
}
