<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;

class OtherEnumType extends EnumType
{
    public function __construct()
    {
        $config = [
            'name'   => 'OtherEnum',
            'values' => [
                'ONE',
                'TWO',
                'THREE',
            ],
        ];
        parent::__construct($config);
    }

    /**
     * @param mixed[]|null $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        $value = parent::parseLiteral($valueNode, $variables);

        return new OtherEnum($value);
    }

    public function serialize($value)
    {
        if ($value instanceof OtherEnum) {
            $value = $value->getValue();
        }

        return parent::serialize($value);
    }
}
