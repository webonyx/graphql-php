<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\UnionType;

class SearchResultType extends UnionType
{
    public function __construct()
    {
        $config = [
            'name' => 'SearchResultType',
            'types' => static function () {
                return [
                    Types::story(),
                    Types::user(),
                ];
            },
            'resolveType' => static function ($value) {
                if ($value instanceof Story) {
                    return Types::story();
                }

                if ($value instanceof User) {
                    return Types::user();
                }
            },
        ];
        parent::__construct($config);
    }
}
