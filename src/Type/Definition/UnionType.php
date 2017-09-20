<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class UnionType
 * @package GraphQL\Type\Definition
 */
class UnionType extends Type implements AbstractType, OutputType, CompositeType
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

        Utils::assertValidName($config['name']);

        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'types' => Config::arrayOf(Config::OBJECT_TYPE, Config::MAYBE_THUNK | Config::REQUIRED),
            'resolveType' => Config::CALLBACK, // function($value, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

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
    public function getPossibleTypes()
    {
        trigger_error(__METHOD__ . ' is deprecated in favor of ' . __CLASS__ . '::getTypes()', E_USER_DEPRECATED);
        return $this->getTypes();
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
                    "{$this->name} types must be an Array or a callable which returns an Array."
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

        $types = $this->getTypes();
        Utils::invariant(
            !empty($types),
            "{$this->name} types must not be empty"
        );

        if (isset($this->config['resolveType'])) {
            Utils::invariant(
                is_callable($this->config['resolveType']),
                "{$this->name} must provide \"resolveType\" as a function."
            );
        }

        $includedTypeNames = [];
        foreach ($types as $objType) {
            Utils::invariant(
                $objType instanceof ObjectType,
                "{$this->name} may only contain Object types, it cannot contain: %s.",
                Utils::printSafe($objType)
            );
            Utils::invariant(
                !isset($includedTypeNames[$objType->name]),
                "{$this->name} can include {$objType->name} type only once."
            );
            $includedTypeNames[$objType->name] = true;
            if (!isset($this->config['resolveType'])) {
                Utils::invariant(
                    isset($objType->config['isTypeOf']) && is_callable($objType->config['isTypeOf']),
                    "Union type \"{$this->name}\" does not provide a \"resolveType\" " .
                    "function and possible type \"{$objType->name}\" does not provide an " .
                    '"isTypeOf" function. There is no way to resolve this possible type ' .
                    'during execution.'
                );
            }
        }
    }
}
