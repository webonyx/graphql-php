<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class UnionType
 * @package GraphQL\Type\Definition
 */
class UnionType extends Type implements AbstractType, OutputType, CompositeType, NamedType
{
    /**
     * @var UnionTypeDefinitionNode
     */
    public $astNode;

    /**
     * @var ObjectType[]
     */
    private $types;

    /**
     * @var ObjectType[]
     */
    private $possibleTypeNames;

    /**
     * UnionType constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        /**
         * Optionally provide a custom type resolver function. If one is not provided,
         * the default implemenation will call `isTypeOf` on each implementing
         * Object type.
         */
        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->config = $config;
    }

    /**
     * @return ObjectType[]
     */
    public function getTypes()
    {
        if (null === $this->types) {
            if (!isset($this->config['types'])) {
                $types = null;
            } else if (is_callable($this->config['types'])) {
                $types = call_user_func($this->config['types']);
            } else {
                $types = $this->config['types'];
            }

            if (!is_array($types)) {
                throw new InvariantViolation(
                    "Must provide Array of types or a callable which returns " .
                    "such an array for Union {$this->name}"
                );
            }

            $this->types = $types;
        }
        return $this->types;
    }

    /**
     * @param Type $type
     * @return mixed
     */
    public function isPossibleType(Type $type)
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        if (null === $this->possibleTypeNames) {
            $this->possibleTypeNames = [];
            foreach ($this->getTypes() as $possibleType) {
                $this->possibleTypeNames[$possibleType->name] = true;
            }
        }
        return isset($this->possibleTypeNames[$type->name]);
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

        if (isset($this->config['resolveType'])) {
            Utils::invariant(
                is_callable($this->config['resolveType']),
                "{$this->name} must provide \"resolveType\" as a function."
            );
        }
    }
}
