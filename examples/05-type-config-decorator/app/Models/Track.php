<?php declare(strict_types=1);

namespace App\Models;

use Core\Model;

final class Track extends Model
{
    /**
     * @throws \RuntimeException
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::get('tracks');
    }
}
