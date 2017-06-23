<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\InterfaceType;

class NodeType extends InterfaceType
{
    public function __construct()
    {
        $config = [
            'name' => 'Node',
            'fields' => [
                'id' => Types::id()
            ],
            'resolveType' => [$this, 'resolveNodeType']
        ];
        parent::__construct($config);
    }

    public function resolveNodeType($object)
    {
        if ($object instanceof User) {
            return Types::user();
        } elseif ($object instanceof Image) {
            return Types::image();
        } elseif ($object instanceof Story) {
            return Types::story();
        }
    }
}
