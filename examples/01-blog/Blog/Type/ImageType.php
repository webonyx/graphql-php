<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;

class ImageType
{
    public static function getDefinition(TypeSystem $types)
    {
        $handler = new self();

        return new ObjectType([
            'name' => 'ImageType',
            'fields' => [
                'id' => $types->id(),
                'type' => new EnumType([
                    'name' => 'ImageTypeEnum',
                    'values' => [
                        'USERPIC' => Image::TYPE_USERPIC
                    ]
                ]),
                'size' => $types->imageSizeEnum(),
                'width' => $types->int(),
                'height' => $types->int(),
                'url' => [
                    'type' => $types->url(),
                    'resolve' => [$handler, 'resolveUrl']
                ],
                'error' => [
                    'type' => $types->string(),
                    'resolve' => function() {
                        throw new \Exception("This is error field");
                    }
                ]
            ]
        ]);
    }

    public function resolveUrl(Image $value, $args, AppContext $context)
    {
        switch ($value->type) {
            case Image::TYPE_USERPIC:
                $path = "/images/user/{$value->id}-{$value->size}.jpg";
                break;
            default:
                throw new \UnexpectedValueException("Unexpected image type: " . $value->type);
        }
        return $context->rootUrl . $path;
    }
}
