<?php

declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use GraphQL\Validator\Rules\AbstractValidationRule;
use function is_array;
use function is_callable;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * Server configuration class.
 * Could be passed directly to server constructor. List of options accepted by **create** method is
 * [described in docs](executing-queries.md#server-configuration-options).
 *
 * Usage example:
 *
 *     $config = GraphQL\Server\ServerConfig::create()
 *         ->setSchema($mySchema)
 *         ->setContext($myContext);
 *
 *     $server = new GraphQL\Server\StandardServer($config);
 */
class ServerConfig
{
    /**
     * Converts an array of options to instance of ServerConfig
     * (or just returns empty config when array is not passed).
     *
     * @api
     * @param mixed[] $config
     * @return ServerConfig
     */
    public static function create(array $config = [])
    {
        $instance = new static();
        foreach ($config as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (! method_exists($instance, $method)) {
                throw new InvariantViolation(sprintf('Unknown server config option "%s"', $key));
            }
            $instance->$method($value);
        }

        return $instance;
    }

    /** @var Schema */
    private $schema;

    /** @var mixed|\Closure */
    private $context;

    /** @var mixed|\Closure */
    private $rootValue;

    /** @var callable|null */
    private $errorFormatter;

    /** @var callable|null */
    private $errorsHandler;

    /** @var bool */
    private $debug = false;

    /** @var bool */
    private $queryBatching = false;

    /** @var AbstractValidationRule[]|callable */
    private $validationRules;

    /** @var callable */
    private $fieldResolver;

    /** @var PromiseAdapter */
    private $promiseAdapter;

    /** @var callable */
    private $persistentQueryLoader;

    /**
     * @api
     * @return self
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @api
     * @param mixed|\Closure $context
     * @return self
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @api
     * @param mixed|\Closure $rootValue
     * @return self
     */
    public function setRootValue($rootValue)
    {
        $this->rootValue = $rootValue;

        return $this;
    }

    /**
     * Expects function(Throwable $e) : array
     *
     * @api
     * @return self
     */
    public function setErrorFormatter(callable $errorFormatter)
    {
        $this->errorFormatter = $errorFormatter;

        return $this;
    }

    /**
     * Expects function(array $errors, callable $formatter) : array
     *
     * @api
     * @return self
     */
    public function setErrorsHandler(callable $handler)
    {
        $this->errorsHandler = $handler;

        return $this;
    }

    /**
     * Set validation rules for this server.
     *
     * @api
     * @param AbstractValidationRule[]|callable $validationRules
     * @return self
     */
    public function setValidationRules($validationRules)
    {
        if (! is_callable($validationRules) && ! is_array($validationRules) && $validationRules !== null) {
            throw new InvariantViolation(
                'Server config expects array of validation rules or callable returning such array, but got ' .
                Utils::printSafe($validationRules)
            );
        }

        $this->validationRules = $validationRules;

        return $this;
    }

    /**
     * @api
     * @return self
     */
    public function setFieldResolver(callable $fieldResolver)
    {
        $this->fieldResolver = $fieldResolver;

        return $this;
    }

    /**
     * Expects function($queryId, OperationParams $params) : string|DocumentNode
     *
     * This function must return query string or valid DocumentNode.
     *
     * @api
     * @return self
     */
    public function setPersistentQueryLoader(callable $persistentQueryLoader)
    {
        $this->persistentQueryLoader = $persistentQueryLoader;

        return $this;
    }

    /**
     * Set response debug flags. See GraphQL\Error\Debug class for a list of all available flags
     *
     * @api
     * @param bool|int $set
     * @return self
     */
    public function setDebug($set = true)
    {
        $this->debug = $set;

        return $this;
    }

    /**
     * Allow batching queries (disabled by default)
     *
     * @api
     * @param bool $enableBatching
     * @return self
     */
    public function setQueryBatching($enableBatching)
    {
        $this->queryBatching = (bool) $enableBatching;

        return $this;
    }

    /**
     * @api
     * @return self
     */
    public function setPromiseAdapter(PromiseAdapter $promiseAdapter)
    {
        $this->promiseAdapter = $promiseAdapter;

        return $this;
    }

    /**
     * @return mixed|callable
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return mixed|callable
     */
    public function getRootValue()
    {
        return $this->rootValue;
    }

    /**
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @return callable|null
     */
    public function getErrorFormatter()
    {
        return $this->errorFormatter;
    }

    /**
     * @return callable|null
     */
    public function getErrorsHandler()
    {
        return $this->errorsHandler;
    }

    /**
     * @return PromiseAdapter
     */
    public function getPromiseAdapter()
    {
        return $this->promiseAdapter;
    }

    /**
     * @return AbstractValidationRule[]|callable
     */
    public function getValidationRules()
    {
        return $this->validationRules;
    }

    /**
     * @return callable
     */
    public function getFieldResolver()
    {
        return $this->fieldResolver;
    }

    /**
     * @return callable
     */
    public function getPersistentQueryLoader()
    {
        return $this->persistentQueryLoader;
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @return bool
     */
    public function getQueryBatching()
    {
        return $this->queryBatching;
    }
}
