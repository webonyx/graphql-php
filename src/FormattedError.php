<?php
namespace GraphQL;

class FormattedError
{
    /**
     * @var string
     */
    public $message;

    /**
     * @var array<Language\SourceLocation>
     */
    public $locations;

    /**
     * @param $message
     * @param array<Language\SourceLocation> $locations
     */
    public function __construct($message, $locations = [])
    {
        $this->message = $message;
        $this->locations = array_map(function($loc) { return $loc->toArray();}, $locations);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'message' => $this->message,
            'locations' => $this->locations
        ];
    }
}
