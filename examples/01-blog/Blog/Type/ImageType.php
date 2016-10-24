<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;

class ImageType extends ObjectType
{
    public function __construct(TypeSystem $types)
    {
        // Option #2: define type using inheritance, see any other object type for compositional example
        $config = [
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
                    'resolve' => [$this, 'resolveUrl']
                ],

                // Just for the sake of example
                'fieldWithError' => [
                    'type' => $types->string(),
                    'resolve' => function() {
                        throw new \Exception("Field with exception");
                    }
                ],
                'nonNullFieldWithError' => [
                    'type' => $types->nonNull($types->string()),
                    'resolve' => function() {
                        throw new \Exception("Non-null field with exception");
                    }
                ]
            ]
        ];

        parent::__construct($config);
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
