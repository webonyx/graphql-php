<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use Exception;
use function get_class;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class NodeType extends InterfaceType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Node',
            'fields' => [
                'id' => Types::id(),
            ],
            'resolveType' => [$this, 'resolveNodeType'],
        ]);
    }

    public function resolveNodeType(object $object): Type
    {
        if ($object instanceof User) {
            return Types::user();
        }

        if ($object instanceof Image) {
            return Types::image();
        }

        if ($object instanceof Story) {
            return Types::story();
        }

        throw new Exception('Unknown type: ' . get_class($object));
    }
}
