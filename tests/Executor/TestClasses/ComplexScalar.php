<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Printer;
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
        if ($value === 'DeserializedValue') {
            return 'SerializedValue';
        }

        $notComplexScalar = Utils::printSafe($value);
        throw new SerializationError("Cannot serialize value as ComplexScalar: {$notComplexScalar}");
    }

    public function parseValue($value): string
    {
        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        $notComplexScalar = Utils::printSafeJson($value);
        throw new Error("Cannot represent value as ComplexScalar: {$notComplexScalar}");
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        $value = property_exists($valueNode, 'value')
            ? $valueNode->value
            : null;

        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        $notComplexScalar = Printer::doPrint($value);
        throw new Error("Cannot represent literal as ComplexScalar: {$notComplexScalar}");
    }
}
