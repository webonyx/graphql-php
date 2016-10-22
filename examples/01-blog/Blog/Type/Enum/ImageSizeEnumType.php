<?php
namespace GraphQL\Examples\Blog\Type\Enum;

use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Type\BaseType;
use GraphQL\Type\Definition\EnumType;

class ImageSizeEnumType extends BaseType
{
    /**
     * ImageSizeEnumType constructor.
     */
    public function __construct()
    {
        $this->definition = new EnumType([
            'name' => 'ImageSizeEnum',
            'values' => [
                'ICON' => Image::SIZE_ICON,
                'SMALL' => Image::SIZE_SMALL,
                'MEDIUM' => Image::SIZE_MEDIUM,
                'ORIGINAL' => Image::SIZE_ORIGINAL
            ]
        ]);
    }
}
