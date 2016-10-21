<?php
namespace GraphQL\Examples\Blog\Type\Enum;

use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Type\Definition\EnumType;

class ImageSizeEnumType
{
    /**
     * @return EnumType
     */
    public static function getDefinition()
    {
        return new EnumType([
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
