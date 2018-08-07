<?php
namespace GraphQL\Type\Definition;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class InputObjectField
 * @package GraphQL\Type\Definition
 */
class InputObjectField
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var callback|InputType
     */
    public $type;

    /**
     * @var InputValueDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * Helps to differentiate when `defaultValue` is `null` and when it was not even set initially
     *
     * @var bool
     */
    private $defaultValueExists = false;

    /**
     * InputObjectField constructor.
     * @param array $opts
     */
    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            switch ($k) {
                case 'defaultValue':
                    $this->defaultValue = $v;
                    $this->defaultValueExists = true;
                    break;
                case 'defaultValueExists':
                    break;
                default:
                    $this->{$k} = $v;
            }
        }
        $this->config = $opts;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function defaultValueExists()
    {
        return $this->defaultValueExists;
    }

    /**
     * @param Type $parentType
     * @throws InvariantViolation
     */
    public function assertValid(Type $parentType)
    {
        try {
            Utils::assertValidName($this->name);
        } catch (Error $e) {
            throw new InvariantViolation("{$parentType->name}.{$this->name}: {$e->getMessage()}");
        }
        $type = $this->type;
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }
        Utils::invariant(
            $type instanceof InputType,
            "{$parentType->name}.{$this->name} field type must be Input Type but got: " . Utils::printSafe($this->type)
        );
        Utils::invariant(
            empty($this->config['resolve']),
            "{$parentType->name}.{$this->name} field type has a resolve property, " .
            'but Input Types cannot define resolvers.'
        );
    }
}
