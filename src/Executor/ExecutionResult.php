<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

/**
 * Returned after [query execution](executing-queries.md).
 * Represents both - result of successful execution and of a failed one
 * (with errors collected in `errors` prop)
 *
 * Could be converted to [spec-compliant](https://facebook.github.io/graphql/#sec-Response-Format)
 * serializable array using `toArray()`
 */
class ExecutionResult implements \JsonSerializable
{
    /**
     * Data collected from resolvers during query execution
     *
     * @api
     * @var array
     */
    public $data;

    /**
     * Errors registered during query execution.
     *
     * If an error was caused by exception thrown in resolver, $error->getPrevious() would
     * contain original exception.
     *
     * @api
     * @var \GraphQL\Error\Error[]
     */
    public $errors;
    
    /**
     * User-defined serializable array of extensions included in serialized result.
     * Conforms to
     *
     * @api
     * @var array
     */
    public $extensions;

    /**
     * @var callable
     */
    private $errorFormatter;

    /**
     * @var callable
     */
    private $errorsHandler;

    /**
     * @param array $data
     * @param array $errors
     * @param array $extensions
     */
    public function __construct(array $data = null, array $errors = [], array $extensions = [])
    {
        $this->data = $data;
        $this->errors = $errors;
        $this->extensions = $extensions;
    }

    /**
     * Define custom error formatting (must conform to http://facebook.github.io/graphql/#sec-Errors)
     *
     * Expected signature is: function (GraphQL\Error\Error $error): array
     *
     * Default formatter is "GraphQL\Error\FormattedError::createFromException"
     *
     * Expected returned value must be an array:
     * array(
     *    'message' => 'errorMessage',
     *    // ... other keys
     * );
     *
     * @api
     * @param callable $errorFormatter
     * @return $this
     */
    public function setErrorFormatter(callable $errorFormatter)
    {
        $this->errorFormatter = $errorFormatter;
        return $this;
    }

    /**
     * Define custom logic for error handling (filtering, logging, etc).
     *
     * Expected handler signature is: function (array $errors, callable $formatter): array
     *
     * Default handler is:
     * function (array $errors, callable $formatter) {
     *     return array_map($formatter, $errors);
     * }
     *
     * @api
     * @param callable $handler
     * @return $this
     */
    public function setErrorsHandler(callable $handler)
    {
        $this->errorsHandler = $handler;
        return $this;
    }

    /**
     * Converts GraphQL query result to spec-compliant serializable array using provided
     * errors handler and formatter.
     *
     * If debug argument is passed, output of error formatter is enriched which debugging information
     * ("debugMessage", "trace" keys depending on flags).
     *
     * $debug argument must be either bool (only adds "debugMessage" to result) or sum of flags from
     * GraphQL\Error\Debug
     *
     * @api
     * @param bool|int $debug
     * @return array
     */
    public function toArray($debug = false)
    {
        $result = [];

        if (!empty($this->errors)) {
            $errorsHandler = $this->errorsHandler ?: function(array $errors, callable $formatter) {
                return array_map($formatter, $errors);
            };
            $result['errors'] = $errorsHandler(
                $this->errors,
                FormattedError::prepareFormatter($this->errorFormatter, $debug)
            );
        }

        if (null !== $this->data) {
            $result['data'] = $this->data;
        }
        
        if (!empty($this->extensions)) {
            $result['extensions'] = (array) $this->extensions;
        }

        return $result;
    }

    /**
     * Part of \JsonSerializable interface
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
