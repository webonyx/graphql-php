<?php

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FileType
 *
 * @package GraphQL\Type\Definition
 */
class FileType extends ScalarType
{
    /**
     * @var string
     */
    public $name = Type::FILE;

    /**
     * @var string
     */
    public $description =
        'The `File` special type represents a file to be uploaded in the same HTTP query as specified by
 [graphql-multipart-request-spec](https://github.com/jaydenseric/graphql-multipart-request-spec).';

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serialize($value)
    {
        throw new InvariantViolation('FileType cannot be represented as string');
    }

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function parseValue($value)
    {
        if (!$value instanceof UploadedFileInterface) {
            throw new \UnexpectedValueException("Could not get uploaded file, be sure to conform to GraphQL multipart request specification. Instead got: " . Utils::printSafe($value));
        }

        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     *
     * @return mixed
     */
    public function parseLiteral($valueNode)
    {
        throw new Error('File cannot be hardcoded in query, be sure to conform to GraphQL multipart request specification. Instead got: ' . $valueNode->kind, [$valueNode]);
    }
}
