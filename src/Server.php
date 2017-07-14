<?php
namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Resolution;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Utils\Utils;

trigger_error(
    'GraphQL\Server is deprecated in favor of new implementation: GraphQL\Server\StandardServer and will be removed in next version',
    E_USER_DEPRECATED
);

class Server
{
    const DEBUG_PHP_ERRORS = 1;
    const DEBUG_EXCEPTIONS = 2;
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
        $this->assertSchemaNotSet('Query Type', __METHOD__);
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
        $this->assertSchemaNotSet('Mutation Type', __METHOD__);
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
        $this->assertSchemaNotSet('Subscription Type', __METHOD__);
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    /**
     * @param Type[] $types
     * @return Server
     */
    public function addTypes(array $types)
    {
        if (!empty($types)) {
            $this->assertSchemaNotSet('Types', __METHOD__);
            $this->types = array_merge($this->types, $types);
        }
        return $this;
    }

    /**
     * @return array
     */
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
        $this->assertSchemaNotSet('Directives', __METHOD__);
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
     * Set schema instance manually. Mutually exclusive with `setQueryType`, `setMutationType`, `setSubscriptionType`, `setDirectives`, `addTypes`
     *
     * @param Schema $schema
     * @return $this
     */
    public function setSchema(Schema $schema)
    {
        if ($this->queryType) {
            $err = 'Query Type is already set';
            $errMethod = __CLASS__ . '::setQueryType';
        } else if ($this->mutationType) {
            $err = 'Mutation Type is already set';
            $errMethod = __CLASS__ . '::setMutationType';
        } else if ($this->subscriptionType) {
            $err = 'Subscription Type is already set';
            $errMethod = __CLASS__ . '::setSubscriptionType';
        } else if ($this->directives) {
            $err = 'Directives are already set';
            $errMethod = __CLASS__ . '::setDirectives';
        } else if ($this->types) {
            $err = 'Additional types are already set';
            $errMethod = __CLASS__ . '::addTypes';
        } else if ($this->typeResolutionStrategy) {
            $err = 'Type Resolution Strategy is already set';
            $errMethod = __CLASS__ . '::setTypeResolutionStrategy';
        } else if ($this->schema && $this->schema !== $schema) {
            $err = 'Different schema is already set';
        }

        if (isset($err)) {
            if (isset($errMethod)) {
                $err .= " ($errMethod is mutually exclusive with " . __METHOD__ . ")";
            }
            throw new InvariantViolation("Cannot set Schema on Server: $err");
        }
        $this->schema = $schema;
        return $this;
    }

    /**
     * @param $entry
     * @param $mutuallyExclusiveMethod
     */
    private function assertSchemaNotSet($entry, $mutuallyExclusiveMethod)
    {
        if ($this->schema) {
            $schemaMethod = __CLASS__ . '::setSchema';
            throw new InvariantViolation("Cannot set $entry on Server: Schema is already set ($mutuallyExclusiveMethod is mutually exclusive with $schemaMethod)");
        }
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
     * Expects function(\ErrorException $e) : array
     *
     * @param callable $phpErrorFormatter
     * @return $this
     */
    public function setPhpErrorFormatter(callable $phpErrorFormatter)
    {
        $this->phpErrorFormatter = $phpErrorFormatter;
        return $this;
    }

    /**
     * @return callable
     */
    public function getExceptionFormatter()
    {
        return $this->exceptionFormatter;
    }

    /**
     * Expects function(Exception $e) : array
     *
     * @param callable $exceptionFormatter
     * @return $this
     */
    public function setExceptionFormatter(callable $exceptionFormatter)
    {
        $this->exceptionFormatter = $exceptionFormatter;
        return $this;
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
     * @return $this
     */
    public function setUnexpectedErrorMessage($unexpectedErrorMessage)
    {
        $this->unexpectedErrorMessage = $unexpectedErrorMessage;
        return $this;
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
     * @return $this
     */
    public function setUnexpectedErrorStatus($unexpectedErrorStatus)
    {
        $this->unexpectedErrorStatus = $unexpectedErrorStatus;
        return $this;
    }

    /**
     * Parses GraphQL query string and returns Abstract Syntax Tree for this query
     *
     * @param string $query
     * @return Language\AST\DocumentNode
     * @throws \GraphQL\Error\SyntaxError
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
     * @return $this
     */
    public function setValidationRules(array $validationRules)
    {
        $this->validationRules = $validationRules;
        return $this;
    }

    /**
     * EXPERIMENTAL!
     * This method can be removed or changed in future versions without a prior notice.
     *
     * @return Resolution
     */
    public function getTypeResolutionStrategy()
    {
        return $this->typeResolutionStrategy;
    }

    /**
     * EXPERIMENTAL!
     * This method can be removed or changed in future versions without a prior notice.
     *
     * @param Resolution $typeResolutionStrategy
     * @return Server
     */
    public function setTypeResolutionStrategy(Resolution $typeResolutionStrategy)
    {
        $this->assertSchemaNotSet('Type Resolution Strategy', __METHOD__);
        $this->typeResolutionStrategy = $typeResolutionStrategy;
        return $this;
    }

    /**
     * @return PromiseAdapter|null
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
        if ($this->promiseAdapter && $promiseAdapter !== $this->promiseAdapter) {
            throw new InvariantViolation("Cannot set promise adapter: Different adapter is already set");
        }
        $this->promiseAdapter = $promiseAdapter;
        return $this;
    }

    /**
     * Returns array with validation errors (empty when there are no validation errors)
     *
     * @param DocumentNode $query
     * @return array
     */
    public function validate(DocumentNode $query)
    {
        try {
            $schema = $this->getSchema();
        } catch (InvariantViolation $e) {
            throw new InvariantViolation("Cannot validate, schema contains errors: {$e->getMessage()}", null, $e);
        }

        return DocumentValidator::validate($schema, $query, $this->validationRules);
    }

    /**
     * Executes GraphQL query against this server and returns execution result
     *
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
            set_error_handler(function($severity, $message, $file, $line) {
                $this->phpErrors[] = new \ErrorException($message, 0, $severity, $file, $line);
            });
        }

        if ($this->promiseAdapter) {
            // TODO: inline GraphQL::executeAndReturnResult and pass promise adapter to executor constructor directly
            $promiseAdapter = Executor::getPromiseAdapter();
            Executor::setPromiseAdapter($this->promiseAdapter);
        }

        $result = GraphQL::executeAndReturnResult(
            $this->getSchema(),
            $query,
            $this->getRootValue(),
            $this->getContext(),
            $variables,
            $operationName
        );

        if (isset($promiseAdapter)) {
            Executor::setPromiseAdapter($promiseAdapter);
        }

        // Add details about original exception in error entry (if any)
        if ($this->debug & static::DEBUG_EXCEPTIONS) {
            $result->setErrorFormatter([$this, 'formatError']);
        }

        // Add reported PHP errors to result (if any)
        if (!empty($this->phpErrors) && ($this->debug & static::DEBUG_PHP_ERRORS)) {
            $result->extensions['phpErrors'] = array_map($this->phpErrorFormatter, $this->phpErrors);
        }

        if ($this->debug & static::DEBUG_PHP_ERRORS) {
            restore_error_handler();
        }
        return $result;
    }

    /**
     * GraphQL HTTP endpoint compatible with express-graphql
     */
    public function handleRequest()
    {
        try {
            $httpStatus = 200;
            if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                $raw = $this->readInput();
                $data = json_decode($raw, true);

                Utils::invariant(
                    is_array($data),
                    "GraphQL Server expects JSON object with keys 'query' and 'variables', but got " . Utils::getVariableType($data)
                );
            } else {
                $data = $_REQUEST ?: [];
            }
            $data += ['query' => null, 'variables' => null];
            $result = $this->executeQuery($data['query'], (array) $data['variables'])->toArray();
        } catch (\Exception $e) {
            // This is only possible for schema creation errors and some very unpredictable errors,
            // (all errors which occur during query execution are caught and included in final response)
            $httpStatus = $this->unexpectedErrorStatus;
            $error = new Error($this->unexpectedErrorMessage, null, null, null, null, $e);
            $result = ['errors' => [$this->formatError($error)]];
        } catch (\Throwable $e) {
            $httpStatus = $this->unexpectedErrorStatus;
            $error = new Error($this->unexpectedErrorMessage, null, null, null, null, $e);
            $result = ['errors' => [$this->formatError($error)]];
        }

        $this->produceOutput($result, $httpStatus);
    }

    /**
    * @param \Throwable $e
    */
    private function formatException($e)
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

    protected function readInput()
    {
        return  file_get_contents('php://input') ?: '';
    }

    protected function produceOutput(array $result, $httpStatus)
    {
        header('Content-Type: application/json', true, $httpStatus);
        echo json_encode($result);
    }
}
