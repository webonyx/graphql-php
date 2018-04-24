<?php
namespace GraphQL\Tests\Executor;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class Dog
{
    function __construct($name, $woofs)
    {
        $this->name = $name;
        $this->woofs = $woofs;
    }
}

class Cat
{
    function __construct($name, $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
    }
}

class Human
{
    function __construct($name)
    {
        $this->name = $name;
    }
}

class Person
{
    public $name;
    public $pets;
    public $friends;

    function __construct($name, $pets = null, $friends = null)
    {
        $this->name = $name;
        $this->pets = $pets;
        $this->friends = $friends;
    }
}

class ComplexScalar extends ScalarType
{
    public static function create()
    {
        return new self();
    }

    public $name = 'ComplexScalar';

    /**
     * {@inheritdoc}
     */
    public function serialize($value)
    {
        if ($value === 'DeserializedValue') {
            return 'SerializedValue';
        }

        throw new Error("Cannot serialize value as ComplexScalar: " . Utils::printSafe($value));
    }

    /**
     * {@inheritdoc}
     */
    public function parseValue($value)
    {
        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error("Cannot represent value as ComplexScalar: " . Utils::printSafe($value));
    }

    /**
     * {@inheritdoc}
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode->value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error("Cannot represent literal as ComplexScalar: " . Utils::printSafe($valueNode->value));
    }
}

class Special
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class NotSpecial
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class Adder
{
    public $num;

    public $test;

    public function __construct($num)
    {
        $this->num = $num;

        $this->test = function($source, $args, $context)  {
            return $this->num + $args['addend1'] + $context['addend2'];
        };
    }
}
