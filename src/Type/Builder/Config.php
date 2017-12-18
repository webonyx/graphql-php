<?php

namespace GraphQL\Type\Builder;

abstract class Config
{
    /**
     * @var array
     */
    private $config;

    protected function __construct()
    {
        $this->config = [];
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    protected function addConfig($name, $value, $append = true)
    {
        if (!$append) {
            $this->config[$name] = $value;
        } else {
            if (!isset($this->config[$name])) {
                $this->config[$name] = [];
            }
            $this->config[$name][] = $value;
        }

        return $this;
    }

    public function build()
    {
        return $this->config;
    }
}
