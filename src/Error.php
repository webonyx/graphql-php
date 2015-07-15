<?php
namespace GraphQL;

use GraphQL\Language\Source;

// /graphql-js/src/error/index.js

class Error extends \Exception
{
    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $stack;

    /**
     * @var array
     */
    public $nodes;

    /**
     * @var array
     */
    public $positions;

    /**
     * @var array<SourceLocation>
     */
    public $locations;

    /**
     * @var Source|null
     */
    public $source;

    /**
     * @param $error
     * @return FormattedError
     */
    public static function formatError($error)
    {
        if (is_array($error)) {
            $message = isset($error['message']) ? $error['message'] : null;
            $locations = isset($error['locations']) ? $error['locations'] : null;
        } else if ($error instanceof Error) {
            $message = $error->message;
            $locations = $error->locations;
        } else {
            $message = (string) $error;
            $locations = null;
        }

        return new FormattedError($message, $locations);
    }

    /**
     * @param string $message
     * @param array|null $nodes
     * @param null $stack
     */
    public function __construct($message, array $nodes = null, $stack = null)
    {
        $this->message = $message;
        $this->stack = $stack ?: $message;

        if ($nodes) {
            $this->nodes = $nodes;
            $positions = array_map(function($node) { return isset($node->loc) ? $node->loc->start : null; }, $nodes);
            $positions = array_filter($positions);

            if (!empty($positions)) {
                $this->positions = $positions;
                $loc = $nodes[0]->loc;
                $source = $loc ? $loc->source : null;
                if ($source) {
                    $this->locations = array_map(function($pos) use($source) {return $source->getLocation($pos);}, $positions);
                    $this->source = $source;
                }
            }
        }
    }
}
