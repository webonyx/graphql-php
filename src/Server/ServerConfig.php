<?php
namespace GraphQL\Server;

use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Schema;
use GraphQL\Utils\Utils;

class ServerConfig
{
    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var mixed
     */
    private $context;

    /**
     * @var mixed
     */
    private $rootValue;

    /**
     * @var callable
     */
    private $errorFormatter;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var array|callable
     */
    private $validationRules;

    /**
     * @var callable
     */
    private $defaultFieldResolver;

    /**
     * @var PromiseAdapter
     */
    private $promiseAdapter;

    /**
     * @var callable
     */
    private $persistentQueryLoader;

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     * @return $this
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @param mixed $rootValue
     * @return $this
     */
    public function setRootValue($rootValue)
    {
        $this->rootValue = $rootValue;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRootValue()
    {
        return $this->rootValue;
    }

    /**
     * Set schema instance
     *
     * @param Schema $schema
     * @return $this
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @return callable
     */
    public function getErrorFormatter()
    {
        return $this->errorFormatter;
    }

    /**
     * Expects function(Throwable $e) : array
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
     * @return PromiseAdapter
     */
    public function getPromiseAdapter()
    {
        return $this->promiseAdapter;
    }

    /**
     * @param PromiseAdapter $promiseAdapter
     * @return $this
     */
    public function setPromiseAdapter(PromiseAdapter $promiseAdapter)
    {
        $this->promiseAdapter = $promiseAdapter;
        return $this;
    }

    /**
     * @return array|callable
     */
    public function getValidationRules()
    {
        return $this->validationRules;
    }

    /**
     * Set validation rules for this server.
     *
     * @param array|callable
     * @return $this
     */
    public function setValidationRules($validationRules)
    {
        if (!is_callable($validationRules) && !is_array($validationRules) && $validationRules !== null) {
            throw new InvariantViolation(
                __METHOD__ . ' expects array of validation rules or callable returning such array, but got: %s' .
                Utils::printSafe($validationRules)
            );
        }

        $this->validationRules = $validationRules;
        return $this;
    }

    /**
     * @return callable
     */
    public function getDefaultFieldResolver()
    {
        return $this->defaultFieldResolver;
    }

    /**
     * @param callable $defaultFieldResolver
     * @return $this
     */
    public function setDefaultFieldResolver(callable $defaultFieldResolver)
    {
        $this->defaultFieldResolver = $defaultFieldResolver;
        return $this;
    }

    /**
     * @return callable
     */
    public function getPersistentQueryLoader()
    {
        return $this->persistentQueryLoader;
    }

    /**
     * A function that takes an input id and returns a valid Document.
     * If provided, this will allow your GraphQL endpoint to execute a document specified via `queryId`.
     *
     * @param callable $persistentQueryLoader
     * @return ServerConfig
     */
    public function setPersistentQueryLoader(callable $persistentQueryLoader)
    {
        $this->persistentQueryLoader = $persistentQueryLoader;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Settings this option has two effects:
     *
     * 1. Replaces current error formatter with the one for debugging (has precedence over `setErrorFormatter()`).
     *    This error formatter adds `trace` entry for all errors in ExecutionResult when it is converted to array.
     *
     * 2. All PHP errors are intercepted during query execution (including warnings, notices and deprecations).
     *
     *    These PHP errors are converted to arrays with `message`, `file`, `line`, `trace` keys and then added to
     *    `extensions` section of ExecutionResult under key `phpErrors`.
     *
     *    After query execution error handler will be removed from stack,
     *    so any errors occurring after execution will not be caught.
     *
     * Use this feature for development and debugging only.
     *
     * @param bool $set
     * @return $this
     */
    public function setDebug($set = true)
    {
        $this->debug = (bool) $set;
        return $this;
    }
}
