<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function array_key_exists;
use function assert;
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
    public $type;

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
                default:
                    $this->{$k} = $v;
            }
        }
        $this->config = $opts;
    }

    /**
     * @return Type&InputType
     */
    public function getType() : Type
    {
        $type = Schema::resolveType($this->type);
        assert(
            $type instanceof InputType,
            new InvariantViolation(sprintf('Expected type of InputObjectField to implement InputType but got: %s', Utils::printSafe($type)))
        );

        return $type;
    }

    public function defaultValueExists() : bool
    {
        return array_key_exists('defaultValue', $this->config);
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
        $type = $this->type;
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
            empty($this->config['resolve']),
            sprintf(
                '%s.%s field type has a resolve property, but Input Types cannot define resolvers.',
                $parentType->name,
                $this->name
            )
        );
    }
}
