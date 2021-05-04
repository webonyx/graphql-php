<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use Exception;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

use function get_class;

class SearchResultType extends UnionType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'SearchResultType',
            'types' => static fn (): array => [
                Types::story(),
                Types::user(),
            ],
            'resolveType' => static function (object $value): Type {
                if ($value instanceof Story) {
                    return Types::story();
                }

                if ($value instanceof User) {
                    return Types::user();
                }

                throw new Exception('Unknown type: ' . get_class($value));
            },
        ]);
    }
}
