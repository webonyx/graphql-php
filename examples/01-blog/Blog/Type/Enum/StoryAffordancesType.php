<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class StoryAffordancesType extends EnumType
{
    public const DELETE = 'DELETE';
    public const LIKE = 'LIKE';
    public const UNLIKE = 'UNLIKE';
    public const EDIT = 'EDIT';
    public const REPLY = 'REPLY';

    public function __construct()
    {
        parent::__construct([
            'name' => 'StoryAffordances',
            'values' => [
                self::EDIT,
                self::DELETE,
                self::LIKE,
                self::UNLIKE,
                self::REPLY,
            ],
        ]);
    }
}
