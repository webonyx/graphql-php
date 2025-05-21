<?php

namespace App\Models;

use Core\Model;

class Author extends Model {
  public static function find($id) {
    return static::get("author/$id");
  }
}