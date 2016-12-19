<?php
namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Config;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Resolution;
use GraphQL\Validator\DocumentValidator;

class Server
{
    const DEBUG_PHP_ERRORS = 1;
    const DEBUG_EXCEPTIONS = 2;
    const DEBUG_SCHEMA_CONFIGS = 4;
    const DEBUG_ALL = 7;

    private $queryType;

    private $mutationType;

    private $subscriptionType;

    private $directives;

    private $types = [];

    private $schema;

    private $debug = 0;

    private $contextValue;

    private $rootValue;

    /**
     * @var callable
     */
    private $phpErrorFormatter = ['GraphQL\Error\FormattedError', 'createFromPHPError'];

    /**
     * @var callable
     */
    private $exceptionFormatter = ['GraphQL\Error\FormattedError', 'createFromException'];

    private $unexpectedErrorMessage = 'Unexpected Error';

    private $unexpectedErrorStatus = 500;

    private $validationRules;

    /**
     * @var Resolution
     */
    private $typeResolutionStrategy;

    /**
     * @var PromiseAdapter
     */
    private $promiseAdapter;

    private $phpErrors = [];

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * @return ObjectType|null
     */
    public function getQueryType()
    {
        return $this->queryType;
    }

    /**
     * @param ObjectType $queryType
     * @return Server
     */
    public function setQueryType(ObjectType $queryType)
    {
        $this->queryType = $queryType;
        return $this;
    }

    /**
     * @return ObjectType|null
     */
    public function getMutationType()
    {
        return $this->mutationType;
    }

    /**
     * @param ObjectType $mutationType
     * @return Server
     */
    public function setMutationType(ObjectType $mutationType)
    {
        $this->mutationType = $mutationType;
        return $this;
    }

    /**
     * @return ObjectType|null
     */
    public function getSubscriptionType()
    {
        return $this->subscriptionType;
    }

    /**
     * @param ObjectType $subscriptionType
     * @return Server
     */
    public function setSubscriptionType($subscriptionType)
    {
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    /**
     * @param Type[] $types
     * @return Server
     */
    public function addTypes(array $types)
    {
        $this->types = array_merge($this->types, $types);
        return $this;
    }

    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        if (null === $this->directives) {
            $this->directives = Directive::getInternalDirectives();
        }

        return $this->directives;
    }

    /**
     * @param Directive[] $directives
     * @return Server
     */
    public function setDirectives(array $directives)
    {
        $this->directives = $directives;
        return $this;
    }

    /**
     * @return int
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param int $debug
     * @return Server
     */
    public function setDebug($debug = self::DEBUG_ALL)
    {
        $this->debug = (int) $debug;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->contextValue;
    }

    /**
     * @param mixed $context
     * @return Server
     */
    public function setContext($context)
    {
        $this->contextValue = $context;
        return $this;
    }

    /**
     * @param $rootValue
     * @return Server
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
     * @return Schema
     */
    public function getSchema()
    {
        if (null === $this->schema) {
            $this->schema = new Schema([
                'query' => $this->queryType,
                'mutation' => $this->mutationType,
                'subscription' => $this->subscriptionType,
                'directives' => $this->directives,
                'types' => $this->types,
                'typeResolution' => $this->typeResolutionStrategy
            ]);
        }
        return $this->schema;
    }

    /**
     * @return callable
     */
    public function getPhpErrorFormatter()
    {
        return $this->phpErrorFormatter;
    }

    /**
     * @param callable $phpErrorFormatter
     */
    public function setPhpErrorFormatter(callable $phpErrorFormatter)
    {
        $this->phpErrorFormatter = $phpErrorFormatter;
    }

    /**
     * @return callable
     */
    public function getExceptionFormatter()
    {
        return $this->exceptionFormatter;
    }

    /**
     * @param callable $exceptionFormatter
     */
    public function setExceptionFormatter(callable $exceptionFormatter)
    {
        $this->exceptionFormatter = $exceptionFormatter;
    }

    /**
     * @return string
     */
    public function getUnexpectedErrorMessage()
    {
        return $this->unexpectedErrorMessage;
    }

    /**
     * @param string $unexpectedErrorMessage
     */
    public function setUnexpectedErrorMessage($unexpectedErrorMessage)
    {
        $this->unexpectedErrorMessage = $unexpectedErrorMessage;
    }

    /**
     * @return int
     */
    public function getUnexpectedErrorStatus()
    {
        return $this->unexpectedErrorStatus;
    }

    /**
     * @param int $unexpectedErrorStatus
     */
    public function setUnexpectedErrorStatus($unexpectedErrorStatus)
    {
        $this->unexpectedErrorStatus = $unexpectedErrorStatus;
    }

    /**
     * @param string $query
     * @return Language\AST\DocumentNode
     */
    public function parse($query)
    {
        return Parser::parse($query);
    }

    /**
     * @return array
     */
    public function getValidationRules()
    {
        if (null === $this->validationRules) {
            $this->validationRules = DocumentValidator::allRules();
        }
        return $this->validationRules;
    }

    /**
     * @param array $validationRules
     */
    public function setValidationRules(array $validationRules)
    {
        $this->validationRules = $validationRules;
    }

    /**
     * @return Resolution
     */
    public function getTypeResolutionStrategy()
    {
        return $this->typeResolutionStrategy;
    }

    /**
     * @param Resolution $typeResolutionStrategy
     * @return Server
     */
    public function setTypeResolutionStrategy(Resolution $typeResolutionStrategy)
    {
        $this->typeResolutionStrategy = $typeResolutionStrategy;
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
     * See /docs/data-fetching.md#async-php
     *
     * @param PromiseAdapter $promiseAdapter
     * @return Server
     */
    public function setPromiseAdapter(PromiseAdapter $promiseAdapter)
    {
        $this->promiseAdapter = $promiseAdapter;
        return $this;
    }

    /**
     * Returns array with validation errors
     *
     * @param DocumentNode $query
     * @return array
     */
    public function validate(DocumentNode $query)
    {
        return DocumentValidator::validate($this->getSchema(), $query, $this->validationRules);
    }

    /**
     * @param string|DocumentNode $query
     * @param array|null $variables
     * @param string|null $operationName
     * @return ExecutionResult
     */
    public function executeQuery($query, array $variables = null, $operationName = null)
    {
        $this->phpErrors = [];
        if ($this->debug & static::DEBUG_PHP_ERRORS) {
            // Catch custom errors (to report them in query results)
            $lastDisplayErrors = ini_get('display_errors');
            ini_set('display_errors', 0);

            set_error_handler(function($severity, $message, $file, $line) {
                $this->phpErrors[] = new \ErrorException($message, 0, $severity, $file, $line);
            });
        }
        if ($this->debug & static::DEBUG_SCHEMA_CONFIGS) {
            $isConfigValidationEnabled = Config::isValidationEnabled();
            Config::enableValidation();
        }

        $result = GraphQL::executeAndReturnResult(
            $this->getSchema(),
            $query,
            $this->getRootValue(),
            $this->getContext(),
            $variables,
            $operationName
        );

        // Add details about original exception in error entry (if any)
        if ($this->debug & static::DEBUG_EXCEPTIONS) {
            $result->setErrorFormatter([$this, 'formatError']);
        }

        // Add reported PHP errors to result (if any)
        if (!empty($this->phpErrors) && ($this->debug & static::DEBUG_PHP_ERRORS)) {
            $result->extensions['phpErrors'] = array_map($this->phpErrorFormatter, $this->phpErrors);
        }

        if (isset($lastDisplayErrors)) {
            ini_set('display_errors', $lastDisplayErrors);
            restore_error_handler();
        }
        if (isset($isConfigValidationEnabled) && !$isConfigValidationEnabled) {
            Config::disableValidation();
        }
        return $result;
    }

    public function handleRequest()
    {
        try {
            $httpStatus = 200;
            if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                $raw = file_get_contents('php://input') ?: '';
                $data = json_decode($raw, true);
            } else {
                $data = $_REQUEST;
            }
            $data += ['query' => null, 'variables' => null];
            $result = $this->executeQuery($data['query'], (array) $data['variables'])->toArray();
        } catch (\Exception $exception) {
            // This is only possible for schema creation errors and some very unpredictable errors,
            // (all errors which occur during query execution are caught and included in final response)
            $httpStatus = $this->unexpectedErrorStatus;
            $error = new Error($this->unexpectedErrorMessage, null, null, null, null, $exception);
            $result = ['errors' => [$this->formatError($error)]];
        }

        header('Content-Type: application/json', true, $httpStatus);
        echo json_encode($result);
    }

    private function formatException(\Exception $e)
    {
        $formatter = $this->exceptionFormatter;
        return $formatter($e);
    }

    /**
     * @param Error $e
     * @return array
     */
    public function formatError(\GraphQL\Error\Error $e)
    {
        $result = $e->toSerializableArray();

        if (($this->debug & static::DEBUG_EXCEPTIONS) && $e->getPrevious()) {
            $result['exception'] = $this->formatException($e->getPrevious());
        }
        return $result;
    }
}
