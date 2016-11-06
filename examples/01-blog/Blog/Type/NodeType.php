<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\InterfaceType;

class NodeType extends BaseType
{
    public function __construct()
    {
        // Option #1: using composition over inheritance to define type, see ImageType for inheritance example
        $this->definition = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => Types::id()
            ],
            'resolveType' => [$this, 'resolveType']
        ]);
    }

    public function resolveType($object, Types $types)
    {
        if ($object instanceof User) {
            return Types::user();
        } else if ($object instanceof Image) {
            return Types::image();
        } else if ($object instanceof Story) {
            return Types::story();
        }
    }
}
