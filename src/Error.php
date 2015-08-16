<?php
namespace GraphQL;

use GraphQL\Language\Source;

// /graphql-js/src/error/GraphQLError.js

class Error extends \Exception
{
    /**
     * @var string
     */
    public $message;

    /**
     * @var array
     */
    public $nodes;

    /**
     * @var array
     */
    private $positions;

    /**
     * @var array<SourceLocation>
     */
    private $locations;

    /**
     * @var Source|null
     */
    private $source;

    /**
     * Given an arbitrary Error, presumably thrown while attempting to execute a
     * GraphQL operation, produce a new GraphQLError aware of the location in the
     * document responsible for the original Error.
     *
     * @param $error
     * @param array|null $nodes
     * @return Error
     */
    public static function createLocatedError($error, $nodes = null)
    {
        if ($error instanceof \Exception) {
            $message = $error->getMessage();
            $previous = $error;
        } else {
            $message = (string) $error;
            $previous = null;
        }

        return new Error($message, $nodes, $previous);
    }

    /**
     * @param Error $error
     * @return array
     */
    public static function formatError(Error $error)
    {
        return FormattedError::create($error->getMessage(), $error->getLocations());
    }

    /**
     * @param string|\Exception $message
     * @param array|null $nodes
     * @param Source $source
     * @param null $positions
     */
    public function __construct($message, $nodes = null, \Exception $previous = null, Source $source = null, $positions = null)
    {
        parent::__construct($message, 0, $previous);

        if ($nodes instanceof \Traversable) {
            $nodes = iterator_to_array($nodes);
        }

        $this->nodes = $nodes;
        $this->source = $source;
        $this->positions = $positions;
    }

    /**
     * @return Source|null
     */
    public function getSource()
    {
        if (null === $this->source) {
            if (!empty($this->nodes[0]) && !empty($this->nodes[0]->loc)) {
                $this->source = $this->nodes[0]->loc->source;
            }
        }
        return $this->source;
    }

    /**
     * @return array
     */
    public function getPositions()
    {
        if (null === $this->positions) {
            if (!empty($this->nodes)) {
                $positions = array_map(function($node) { return isset($node->loc) ? $node->loc->start : null; }, $this->nodes);
                $this->positions = array_filter($positions);
            }
        }
        return $this->positions;
    }

    /**
     * @return array<SourceLocation>
     */
    public function getLocations()
    {
        if (null === $this->locations) {
            $positions = $this->getPositions();
            $source = $this->getSource();

            if ($positions && $source) {
                $this->locations = array_map(function ($pos) use ($source) {
                    return $source->getLocation($pos);
                }, $positions);
            } else {
                $this->locations = [];
            }
        }

        return $this->locations;
    }
}
