<?php

namespace App\Models;

use Core\Model;

class Author extends Model {
  public static function find(int $id): array {
    return static::get("author/$id");
  }
}