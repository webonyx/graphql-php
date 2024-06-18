<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeType;
use GraphQL\Examples\Blog\Type\Scalar\UrlType;
use GraphQL\Examples\Blog\TypeRegistry;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ImageType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Image',
            'fields' => [
                'id' => Type::id(),
                'size' => TypeRegistry::type(ImageSizeType::class),
                'width' => Type::int(),
                'height' => Type::int(),
                'url' => [
                    'type' => TypeRegistry::type(UrlType::class),
                    'resolve' => [$this, 'resolveUrl'],
                ],

                // Just for the sake of example
                'fieldWithError' => [
                    'type' => Type::string(),
                    'resolve' => static function (): void {
                        throw new \Exception('Field with exception');
                    },
                ],
                'nonNullFieldWithError' => [
                    'type' => new NonNull(Type::string()),
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
