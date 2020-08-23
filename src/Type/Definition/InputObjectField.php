<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function array_key_exists;
use function sprintf;

class InputObjectField
{
    /** @var string */
    public $name;

    /** @var mixed|null */
    public $defaultValue;

    /** @var string|null */
    public $description;

    /** @var Type&InputType */
    private $type;

    /** @var InputValueDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /**
     * @param mixed[] $opts
     */
    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            switch ($k) {
                case 'defaultValue':
                    $this->defaultValue = $v;
                    break;
                case 'defaultValueExists':
                    break;
                case 'type':
                    // do nothing; type is lazy loaded in getType
                    break;
                default:
                    $this->{$k} = $v;
            }
        }
        $this->config = $opts;
    }

    public function __isset(string $name) : bool
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public getter for 'type' on InputObjectField has been deprecated and will be removed" .
                    " in the next major version. Please update your code to use the 'getType' method.",
                    Warning::WARNING_CONFIG_DEPRECATION
                );

                return isset($this->type);
        }

        return isset($this->$name);
    }

    public function __get(string $name)
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public getter for 'type' on InputObjectField has been deprecated and will be removed" .
                    " in the next major version. Please update your code to use the 'getType' method.",
                    Warning::WARNING_CONFIG_DEPRECATION
                );

                return $this->getType();
            default:
                return $this->$name;
        }

        return null;
    }

    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'type':
                Warning::warnOnce(
                    "The public setter for 'type' on InputObjectField has been deprecated and will be removed" .
                    ' in the next major version.',
                    Warning::WARNING_CONFIG_DEPRECATION
                );
                $this->type = $value;
                break;

            default:
                $this->$name = $value;
                break;
        }
    }

    /**
     * @return Type&InputType
     */
    public function getType() : Type
    {
        if (! isset($this->type)) {
            /**
             * TODO: replace this phpstan cast with native assert
             *
             * @var Type&InputType
             */
            $type       = Schema::resolveType($this->config['type']);
            $this->type = $type;
        }

        return $this->type;
    }

    public function defaultValueExists() : bool
    {
        return array_key_exists('defaultValue', $this->config);
    }

    public function isRequired() : bool
    {
        return $this->getType() instanceof NonNull && ! $this->defaultValueExists();
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid(Type $parentType)
    {
        try {
            Utils::assertValidName($this->name);
        } catch (Error $e) {
            throw new InvariantViolation(sprintf('%s.%s: %s', $parentType->name, $this->name, $e->getMessage()));
        }
        $type = $this->getType();
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }
        Utils::invariant(
            $type instanceof InputType,
            sprintf(
                '%s.%s field type must be Input Type but got: %s',
                $parentType->name,
                $this->name,
                Utils::printSafe($this->type)
            )
        );
        Utils::invariant(
            ! array_key_exists('resolve', $this->config),
            sprintf(
                '%s.%s field has a resolve property, but Input Types cannot define resolvers.',
                $parentType->name,
                $this->name
            )
        );
    }
}
