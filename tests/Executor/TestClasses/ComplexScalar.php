<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class ComplexScalar extends ScalarType
{
    /** @var string */
    public $name = 'ComplexScalar';

    public static function create(): self
    {
        return new self();
    }

    /**
     * @inheritDoc
     */
    public function serialize($value)
    {
        if ($value === 'DeserializedValue') {
            return 'SerializedValue';
        }

        throw new Error('Cannot serialize value as ComplexScalar: ' . Utils::printSafe($value));
    }

    /**
     * @inheritDoc
     */
    public function parseValue($value)
    {
        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent value as ComplexScalar: ' . Utils::printSafe($value));
    }

    /**
     * @inheritDoc
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode->value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent literal as ComplexScalar: ' . Utils::printSafe($valueNode->value));
    }
}
