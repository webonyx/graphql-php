<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class ContentFormatType extends EnumType
{
    const FORMAT_TEXT = 'TEXT';
    const FORMAT_HTML = 'HTML';

    public function __construct()
    {
        parent::__construct([
            'name' => 'ContentFormat',
            'values' => [self::FORMAT_TEXT, self::FORMAT_HTML],
        ]);
    }
}
