<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

class ExecutionResult implements \JsonSerializable
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var Error[]
     */
    public $errors;
    
    /**
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
     * @param callable $handler
     * @return $this
     */
    public function setErrorsHandler(callable $handler)
    {
        $this->errorsHandler = $handler;
        return $this;
    }

    /**
     * Converts GraphQL result to array using provided errors handler and formatter.
     *
     * Default error formatter is GraphQL\Error\FormattedError::createFromException
     * Default error handler will simply return all errors formatted. No errors are filtered.
     *
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
