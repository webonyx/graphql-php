<?php
namespace Core;

use Exception;

class Model {
  protected static $basePath = "https://odyssey-lift-off-rest-api.herokuapp.com/";
  protected static function get(string $endpoint): array {
    $url = static::$basePath . $endpoint;

    $res =  file_get_contents($url);

    if(!$res) {
      throw new Exception("Failed to fetch data from url: $url");
    }
    return json_decode($res, true);
  }
}