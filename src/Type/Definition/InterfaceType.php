<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * Class InterfaceType
 * @package GraphQL\Type\Definition
 */
class InterfaceType extends Type implements AbstractType, OutputType, CompositeType, NamedType
{
    /**
     * @param mixed $type
     * @return self
     */
    public static function assertInterfaceType($type)
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Interface type.'
        );

        return $type;
    }

    /**
     * @var FieldDefinition[]
     */
    private $fields;

    /**
     * @var InterfaceTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * @var InterfaceTypeExtensionNode[]
     */
    public $extensionASTNodes;

    /**
     * InterfaceType constructor.
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
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->extensionASTNodes = isset($config['extensionASTNodes']) ? $config['extensionASTNodes'] : null;
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

        Utils::invariant(
            !isset($this->config['resolveType']) || is_callable($this->config['resolveType']),
            "{$this->name} must provide \"resolveType\" as a function."
        );
    }
}
