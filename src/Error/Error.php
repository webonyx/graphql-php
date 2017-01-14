<?php
namespace GraphQL\Error;

use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils;

/**
 * Class Error
 * A GraphQLError describes an Error found during the parse, validate, or
 * execute phases of performing a GraphQL operation. In addition to a message
 * and stack trace, it also includes information about the locations in a
 * GraphQL document and/or execution result that correspond to the Error.
 *
 * @package GraphQL
 */
class Error extends \Exception implements \JsonSerializable
{
    /**
     * A message describing the Error for debugging purposes.
     *
     * @var string
     */
    public $message;

    /**
     * An array of [ line => x, column => y] locations within the source GraphQL document
     * which correspond to this error.
     *
     * Errors during validation often contain multiple locations, for example to
     * point out two things with the same name. Errors during execution include a
     * single location, the field which produced the error.
     *
     * @var SourceLocation[]
     */
    private $locations;

    /**
     * An array describing the JSON-path into the execution response which
     * corresponds to this error. Only included for errors during execution.
     *
     * @var array
     */
    public $path;

    /**
     * An array of GraphQL AST Nodes corresponding to this error.
     *
     * @var array
     */
    public $nodes;

    /**
     * The source GraphQL document corresponding to this error.
     *
     * @var Source|null
     */
    private $source;

    /**
     * @var array
     */
    private $positions;

    /**
     * Given an arbitrary Error, presumably thrown while attempting to execute a
     * GraphQL operation, produce a new GraphQLError aware of the location in the
     * document responsible for the original Error.
     *
     * @param $error
     * @param array|null $nodes
     * @param array|null $path
     * @return Error
     */
    public static function createLocatedError($error, $nodes = null, $path = null)
    {
        if ($error instanceof self && $error->path) {
            return $error;
        }

        $source = $positions = $originalError = null;

        if ($error instanceof self) {
            $message = $error->getMessage();
            $originalError = $error;
            $nodes = $error->nodes ?: $nodes;
            $source = $error->source;
            $positions = $error->positions;
        } else if ($error instanceof \Exception) {
            $message = $error->getMessage();
            $originalError = $error;
        } else if ($error instanceof \Error) {
            $message = $error->getMessage();
            $originalError = $error;
        } else {
            $message = (string) $error;
        }

        return new static(
            $message ?: 'An unknown error occurred.',
            $nodes,
            $source,
            $positions,
            $path,
            $originalError
        );
    }


    /**
     * @param Error $error
     * @return array
     */
    public static function formatError(Error $error)
    {
        return $error->toSerializableArray();
    }

    /**
     * @param string $message
     * @param array|null $nodes
     * @param Source $source
     * @param array|null $positions
     * @param array|null $path
     * @param \Exception|\Error $previous
     */
    public function __construct($message, $nodes = null, Source $source = null, $positions = null, $path = null, $previous = null)
    {
        parent::__construct($message, 0, $previous);

        if ($nodes instanceof \Traversable) {
            $nodes = iterator_to_array($nodes);
        }

        $this->nodes = $nodes;
        $this->source = $source;
        $this->positions = $positions;
        $this->path = $path;
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
                $positions = array_map(function($node) {
                    return isset($node->loc) ? $node->loc->start : null;
                }, $this->nodes);
                $this->positions = array_filter($positions, function($p) {
                    return $p !== null;
                });
            }
        }
        return $this->positions;
    }

    /**
     * @return SourceLocation[]
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

    /**
     * Returns array representation of error suitable for serialization
     *
     * @return array
     */
    public function toSerializableArray()
    {
        $arr = [
            'message' => $this->getMessage(),
        ];

        $locations = Utils::map($this->getLocations(), function(SourceLocation $loc) {
            return $loc->toSerializableArray();
        });

        if (!empty($locations)) {
            $arr['locations'] = $locations;
        }
        if (!empty($this->path)) {
            $arr['path'] = $this->path;
        }

        return $arr;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->toSerializableArray();
    }
}
