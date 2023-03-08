<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class ImageType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Image',
            'fields' => [
                'id' => Types::id(),
                'size' => Types::imageSize(),
                'width' => Types::int(),
                'height' => Types::int(),
                'url' => [
                    'type' => Types::url(),
                    'resolve' => [$this, 'resolveUrl'],
                ],

                // Just for the sake of example
                'fieldWithError' => [
                    'type' => Types::string(),
                    'resolve' => static function (): void {
                        throw new \Exception('Field with exception');
                    },
                ],
                'nonNullFieldWithError' => [
                    'type' => new NonNull(Types::string()),
                    'resolve' => static function (): void {
                        throw new \Exception('Non-null field with exception');
                    },
                ],
            ],
        ]);
    }

    /**
     * @param array<never> $args
     *
     * @throws \UnexpectedValueException
     */
    public function resolveUrl(Image $value, array $args, AppContext $context, ResolveInfo $info): string
    {
        switch ($value->type) {
            case Image::TYPE_USERPIC:
                $path = "/images/user/{$value->id}-{$value->size}.jpg";
                break;
            default:
                throw new \UnexpectedValueException("Unexpected image type: {$value->type}");
        }

        return $context->rootUrl . $path;
    }
}
