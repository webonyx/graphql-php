<?php
namespace GraphQL\Error;

use GraphQL\Language\Source;

class SyntaxError extends Error
{
    /**
     * @param Source $source
     * @param int $position
     * @param string $description
     */
    public function __construct(Source $source, $position, $description)
    {
        parent::__construct(
            "Syntax Error: $description",
            null,
            $source,
            [$position]
        );
    }
}
