<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class ComplexScalar extends ScalarType
{
    public string $name = 'ComplexScalar';

    public static function create(): self
    {
        return new self();
    }

    public function serialize($value): string
    {
        if ('DeserializedValue' === $value) {
            return 'SerializedValue';
        }

        throw new SerializationError('Cannot serialize value as ComplexScalar: ' . Utils::printSafe($value));
    }

    public function parseValue($value): string
    {
        if ('SerializedValue' === $value) {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent value as ComplexScalar: ' . Utils::printSafe($value));
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if ('SerializedValue' === $valueNode->value) {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent literal as ComplexScalar: ' . Utils::printSafe($valueNode->value));
    }
}
