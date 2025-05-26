<?php declare(strict_types=1);

namespace App\Models;

use Core\Model;

final class Author extends Model
{
    /**
     * @throws \RuntimeException
     *
     * @return array<string, mixed>
     */
    public static function find(int $id): array
    {
        return static::get("author/{$id}");
    }
}
