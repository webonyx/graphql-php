<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;

final class OtherEnumType extends EnumType
{
    public const SERIALIZE_RESULT = 'ONE';
    public const PARSE_LITERAL_RESULT = '1';
    public const PARSE_VALUE_RESULT = '2';

    public function __construct()
    {
        parent::__construct([
            'name' => 'OtherEnum',
            'values' => [
                'ONE',
                'TWO',
                'THREE',
            ],
        ]);
    }

    public function serialize($value)
    {
        return self::SERIALIZE_RESULT;
    }

    public function parseValue($value)
    {
        return self::PARSE_VALUE_RESULT;
    }

    public function parseLiteral(Node $valueNode, array $variables = null)
    {
        return self::PARSE_LITERAL_RESULT;
    }
}
