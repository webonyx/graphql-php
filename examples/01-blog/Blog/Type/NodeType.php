<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Image;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\InterfaceType;

class NodeType
{
    /**
     * @param TypeSystem $types
     * @return InterfaceType
     */
    public static function getDefinition(TypeSystem $types)
    {
        return new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => $types->id()
            ],
            'resolveType' => function ($object) use ($types) {
                return self::resolveType($object, $types);
            }
        ]);
    }

    public static function resolveType($object, TypeSystem $types)
    {
        if ($object instanceof User) {
            return $types->user();
        } else if ($object instanceof Image) {
            return $types->image();
        } else if ($object instanceof Story) {
            return $types->story();
        }
    }
}
