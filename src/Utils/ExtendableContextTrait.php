<?php
namespace GraphQL\Utils;

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
}
