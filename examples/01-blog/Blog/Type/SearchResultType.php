<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeRegistry;
use GraphQL\Type\Definition\UnionType;

class SearchResultType extends UnionType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'SearchResult',
            'types' => static fn (): array => [
                TypeRegistry::type(StoryType::class),
                TypeRegistry::type(UserType::class),
            ],
            'resolveType' => static function (object $value): callable {
                if ($value instanceof Story) {
                    return TypeRegistry::type(StoryType::class);
                }

                if ($value instanceof User) {
                    return TypeRegistry::type(UserType::class);
                }

                $unknownType = get_class($value);
                throw new \Exception("Unknown type: {$unknownType}");
            },
        ]);
    }
}
