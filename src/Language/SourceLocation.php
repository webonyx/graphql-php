<?php
namespace GraphQL\Language;

class SourceLocation
{
    public $line;
    public $column;

    public function __construct($line, $col)
    {
        $this->line = $line;
        $this->column = $col;
    }
}
