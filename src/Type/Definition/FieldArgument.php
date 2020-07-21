<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

class FieldArgument
{
    /** @var string */
    public $name;

    /** @var mixed */
    public $defaultValue;

    /** @var string|null */
    public $description;

    /** @var InputValueDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /** @var Type&InputType */
    private $type;

    /** @param mixed[] $def */
    public function __construct(array $def)
    {
        foreach ($def as $key => $value) {
            switch ($key) {
                case 'name':
                    $this->name = $value;
                    break;
                case 'defaultValue':
                    $this->defaultValue = $value;
                    break;
                case 'description':
                    $this->description = $value;
                    break;
                case 'astNode':
                    $this->astNode = $value;
                    break;
            }
        }
        $this->config = $def;
    }

    /**
     * @param mixed[] $config
     *
     * @return FieldArgument[]
     */
    public static function createMap(array $config) : array
    {
        $map = [];
        foreach ($config as $name => $argConfig) {
            if (! is_array($argConfig)) {
                $argConfig = ['type' => $argConfig];
            }
            $map[] = new self($argConfig + ['name' => $name]);
        }

        return $map;
    }

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

    public function assertValid(FieldDefinition $parentField, Type $parentType)
    {
        try {
            Utils::assertValidName($this->name);
        } catch (InvariantViolation $e) {
            throw new InvariantViolation(
                sprintf('%s.%s(%s:) %s', $parentType->name, $parentField->name, $this->name, $e->getMessage())
            );
        }
        $type = $this->getType();
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }
        Utils::invariant(
            $type instanceof InputType,
            sprintf(
                '%s.%s(%s): argument type must be Input Type but got: %s',
                $parentType->name,
                $parentField->name,
                $this->name,
                Utils::printSafe($this->type)
            )
        );
        Utils::invariant(
            $this->description === null || is_string($this->description),
            sprintf(
                '%s.%s(%s): argument description type must be string but got: %s',
                $parentType->name,
                $parentField->name,
                $this->name,
                Utils::printSafe($this->description)
            )
        );
    }
}
