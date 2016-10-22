<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\UnionType;

class MentionType extends BaseType
{
    public function __construct(TypeSystem $types)
    {
        $this->definition = new UnionType([
            'name' => 'Mention',
            'types' => function() use ($types) {
                return [
                    $types->story(),
                    $types->user()
                ];
            },
            'resolveType' => function($value) use ($types) {
                if ($value instanceof Story) {
                    return $types->story();
                } else if ($value instanceof User) {
                    return $types->user();
                }
            }
        ]);
    }
}
