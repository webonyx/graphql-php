<?php

namespace GraphQL\Language\AST;

class FragmentSpread extends Node implements Selection
{
    protected $kind = NodeType::FRAGMENT_SPREAD;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var array<Directive>
     */
    protected $directives;

    /**
     * FragmentSpread constructor.
     *
     * @param Name  $name
     * @param array $directives
     * @param null  $loc
     */
    public function __construct(Name $name, array $directives, $loc = null)
    {
        $this->name = $name;
        $this->directives = $directives;
        $this->loc = $loc;
    }

    /**
     * @return Name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param Name $name
     *
     * @return FragmentSpread
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param array $directives
     *
     * @return FragmentSpread
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }
}
