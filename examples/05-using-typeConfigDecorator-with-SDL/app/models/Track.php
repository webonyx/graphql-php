<?php

namespace App\Models;

use Core\Model;

class Track extends Model {
  public static function all(): array {
    return static::get('tracks');
  }
}