<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeRegistry;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;

class NodeType extends InterfaceType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Node',
            'fields' => [
                'id' => Type::id(),
            ],
            'resolveType' => [$this, 'resolveNodeType'],
        ]);
    }

    /**
     * @param mixed $object
     *
     * @return callable(): ObjectType
     */
    public function resolveNodeType($object)
    {
        if ($object instanceof User) {
            return TypeRegistry::type(UserType::class);
        }

        if ($object instanceof Image) {
            return TypeRegistry::type(ImageType::class);
        }

        if ($object instanceof Story) {
            return TypeRegistry::type(StoryType::class);
        }

        $notNode = Utils::printSafe($object);
        throw new \Exception("Unknown type: {$notNode}");
    }
}
