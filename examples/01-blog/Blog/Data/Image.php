<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Data;

use GraphQL\Utils\Utils;

class Image
{
    public const TYPE_USERPIC = 'userpic';

    public const SIZE_ICON = 'icon';
    public const SIZE_SMALL = 'small';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_ORIGINAL = 'original';

    public int $id;

    public string $type;

    public string $size;

    public int $width;

    public int $height;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
