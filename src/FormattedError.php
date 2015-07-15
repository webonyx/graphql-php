<?php
namespace GraphQL;

class FormattedError
{
    public $message;

    /**
     * @var array<Language\SourceLocation>
     */
    public $locations;

    public function __construct($message, $locations = null)
    {
        $this->message = $message;
        $this->locations = $locations;
    }
}
