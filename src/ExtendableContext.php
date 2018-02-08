<?php
namespace GraphQL;

interface ExtendableContext
{
    public function getExtensions();

    public function getExtension($name);

    public function setExtension($name, $value);
}
