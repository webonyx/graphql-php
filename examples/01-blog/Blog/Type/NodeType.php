<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\InterfaceType;

class NodeType extends BaseType
{
    /**
     * NodeType constructor.
     * @param TypeSystem $types
     */
    public function __construct(TypeSystem $types)
    {
        $this->definition = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => $types->id()
            ],
            'resolveType' => function ($object) use ($types) {
                return $this->resolveType($object, $types);
            }
        ]);
    }

    public function resolveType($object, TypeSystem $types)
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
