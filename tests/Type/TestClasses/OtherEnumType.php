<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;

class OtherEnumType extends EnumType
{
    public const SERIALIZE_RESULT     = 'ONE';
    public const PARSE_LITERAL_RESULT = '1';
    public const PARSE_VALUE_RESULT   = '2';

    public function __construct()
    {
        parent::__construct([
            'name'   => 'OtherEnum',
            'values' => [
                'ONE',
                'TWO',
                'THREE',
            ],
        ]);
    }

    public function serialize($value)
    {
//        die('serialize');
        return self::SERIALIZE_RESULT;
    }

    public function parseValue($value)
    {
        die('parseValue');

        return self::PARSE_VALUE_RESULT;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
//        die('parseLiteral');
        return self::PARSE_LITERAL_RESULT;
    }
}
