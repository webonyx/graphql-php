<?php
namespace GraphQL\Utils;

use GraphQL\ExtendableContext;

trait ExtendableContextTrait
{
  private $extensions = [];

  public function getExtensions()
  {
    return $this->extensions;
  }

  public function getExtension($name)
  {
    if(array_key_exists($name, $this->extensions))
    {
      return $this->extensions[$name];
    }
    return null;
  }

  public function setExtension($name, $value)
  {
    $this->extensions[$name] = $value;
  }

  public static function handler($extensions, $contextValue)
  {
    return $contextValue instanceof ExtendableContext ? $contextValue->getExtensions() : $extensions;
  }
}
