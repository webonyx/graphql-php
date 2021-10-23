<?php

declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use GraphQL\Validator\Rules\ValidationRule;

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
     * @param array<string, mixed> $config
     *
     * @api
     */
    public static function create(array $config = []): self
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

    private ?Schema $schema = null;

    /** @var mixed|callable(self $config, OperationParams $params, DocumentNode $doc): mixed|null */
    private $context = null;

    /** @var mixed|callable(OperationParams $params, DocumentNode $doc, string $operationType): mixed|null */
    private $rootValue = null;

    /** @var callable|null */
    private $errorFormatter = null;

    /** @var callable|null */
    private $errorsHandler = null;

    private int $debugFlag = DebugFlag::NONE;

    private bool $queryBatching = false;

    /** @var array<ValidationRule>|(callable(OperationParams, DocumentNode, string): array<ValidationRule>)|null */
    private $validationRules = null;

    /** @var callable|null */
    private $fieldResolver = null;

    private ?PromiseAdapter $promiseAdapter = null;

    /** @var callable|null */
    private $persistentQueryLoader = null;

    /**
     * @api
     */
    public function setSchema(Schema $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @param mixed|callable $context
     *
     * @api
     */
    public function setContext($context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @param mixed|callable $rootValue
     *
     * @api
     */
    public function setRootValue($rootValue): self
    {
        $this->rootValue = $rootValue;

        return $this;
    }

    /**
     * @param callable(Error): array<string, mixed> $errorFormatter
     *
     * @api
     */
    public function setErrorFormatter(callable $errorFormatter): self
    {
        $this->errorFormatter = $errorFormatter;

        return $this;
    }

    /**
     * @param callable(array<int, Error> $errors, callable(Error): array<string, mixed> $formatter): array<int, array<string, mixed>> $handler
     *
     * @api
     */
    public function setErrorsHandler(callable $handler): self
    {
        $this->errorsHandler = $handler;

        return $this;
    }

    /**
     * Set validation rules for this server.
     *
     * @param array<ValidationRule>|callable|null $validationRules
     *
     * @api
     */
    public function setValidationRules($validationRules): self
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
     */
    public function setFieldResolver(callable $fieldResolver): self
    {
        $this->fieldResolver = $fieldResolver;

        return $this;
    }

    /**
     * @param callable(string $queryId, OperationParams $params): (string|DocumentNode) $persistentQueryLoader
     *
     * @api
     */
    public function setPersistentQueryLoader(callable $persistentQueryLoader): self
    {
        $this->persistentQueryLoader = $persistentQueryLoader;

        return $this;
    }

    /**
     * Set response debug flags.
     *
     * @see \GraphQL\Error\DebugFlag class for a list of all available flags
     *
     * @api
     */
    public function setDebugFlag(int $debugFlag = DebugFlag::INCLUDE_DEBUG_MESSAGE): self
    {
        $this->debugFlag = $debugFlag;

        return $this;
    }

    /**
     * Allow batching queries (disabled by default).
     *
     * @api
     */
    public function setQueryBatching(bool $enableBatching): self
    {
        $this->queryBatching = $enableBatching;

        return $this;
    }

    /**
     * @api
     */
    public function setPromiseAdapter(PromiseAdapter $promiseAdapter): self
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

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function getErrorFormatter(): ?callable
    {
        return $this->errorFormatter;
    }

    public function getErrorsHandler(): ?callable
    {
        return $this->errorsHandler;
    }

    public function getPromiseAdapter(): ?PromiseAdapter
    {
        return $this->promiseAdapter;
    }

    /**
     * @return array<ValidationRule>|(callable(OperationParams, DocumentNode, string): array<ValidationRule>)|null
     */
    public function getValidationRules()
    {
        return $this->validationRules;
    }

    public function getFieldResolver(): ?callable
    {
        return $this->fieldResolver;
    }

    public function getPersistentQueryLoader(): ?callable
    {
        return $this->persistentQueryLoader;
    }

    public function getDebugFlag(): int
    {
        return $this->debugFlag;
    }

    public function getQueryBatching(): bool
    {
        return $this->queryBatching;
    }
}
