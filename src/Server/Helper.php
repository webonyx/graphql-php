<?php
namespace GraphQL\Server;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;
use GraphQL\Validator\DocumentValidator;

/**
 * Class Helper
 * Contains functionality that could be re-used by various server implementations
 *
 * @package GraphQL\Server
 */
class Helper
{
    /**
     * Executes GraphQL operation with given server configuration and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter)
     *
     * @param ServerConfig $config
     * @param OperationParams $op
     *
     * @return ExecutionResult|Promise
     */
    public function executeOperation(ServerConfig $config, OperationParams $op)
    {
        $promiseAdapter = $config->getPromiseAdapter() ?: Executor::getPromiseAdapter();
        $result = $this->promiseToExecuteOperation($promiseAdapter, $config, $op);

        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result);
        }

        return $result;
    }

    /**
     * Executes batched GraphQL operations with shared promise queue
     * (thus, effectively batching deferreds|promises of all queries at once)
     *
     * @param ServerConfig $config
     * @param OperationParams[] $operations
     * @return ExecutionResult[]|Promise
     */
    public function executeBatch(ServerConfig $config, array $operations)
    {
        $promiseAdapter = $config->getPromiseAdapter() ?: Executor::getPromiseAdapter();
        $result = [];

        foreach ($operations as $operation) {
            $result[] = $this->promiseToExecuteOperation($promiseAdapter, $config, $operation);
        }

        $result = $promiseAdapter->all($result);

        // Wait for promised results when using sync promises
        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result);
        }
        return $result;
    }

    /**
     * @param PromiseAdapter $promiseAdapter
     * @param ServerConfig $config
     * @param OperationParams $op
     * @return Promise
     */
    private function promiseToExecuteOperation(PromiseAdapter $promiseAdapter, ServerConfig $config, OperationParams $op)
    {
        $phpErrors = [];

        $execute = function() use ($config, $op, $promiseAdapter) {
            try {
                $doc = $op->queryId ? static::loadPersistedQuery($config, $op) : $op->query;

                if (!$doc instanceof DocumentNode) {
                    $doc = Parser::parse($doc);
                }
                if ($op->isReadOnly() && AST::isMutation($op->operation, $doc)) {
                    throw new UserError("Cannot execute mutation in read-only context");
                }

                $validationErrors = DocumentValidator::validate(
                    $config->getSchema(),
                    $doc,
                    $this->resolveValidationRules($config, $op)
                );

                if (!empty($validationErrors)) {
                    return $promiseAdapter->createFulfilled(
                        new ExecutionResult(null, $validationErrors)
                    );
                } else {
                    return Executor::promiseToExecute(
                        $promiseAdapter,
                        $config->getSchema(),
                        $doc,
                        $config->getRootValue(),
                        $config->getContext(),
                        $op->variables,
                        $op->operation,
                        $config->getDefaultFieldResolver()
                    );
                }
            } catch (Error $e) {
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, [$e])
                );
            }
        };
        if ($config->getDebug()) {
            $execute = Utils::withErrorHandling($execute, $phpErrors);
        }
        $result = $execute();

        $applyErrorFormatting = function (ExecutionResult $result) use ($config, $phpErrors) {
            if ($config->getDebug()) {
                $errorFormatter = function($e) {
                    return FormattedError::createFromException($e, true);
                };
            } else {
                $errorFormatter = $config->getErrorFormatter();
            }
            if (!empty($phpErrors)) {
                $result->extensions['phpErrors'] = array_map($errorFormatter, $phpErrors);
            }
            $result->setErrorFormatter($errorFormatter);
            return $result;
        };

        return $result->then($applyErrorFormatting);
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $op
     * @return mixed
     * @throws Error
     * @throws InvariantViolation
     */
    public function loadPersistedQuery(ServerConfig $config, OperationParams $op)
    {
        if (!$op->queryId) {
            throw new InvariantViolation("Could not load persisted query: queryId is not set");
        }

        // Load query if we got persisted query id:
        $loader = $config->getPersistentQueryLoader();

        if (!$loader) {
            throw new UserError("Persisted queries are not supported by this server");
        }

        $source = $loader($op->queryId, $op);

        if (!is_string($source) && !$source instanceof DocumentNode) {
            throw new InvariantViolation(sprintf(
                "Persistent query loader must return query string or instance of %s but got: %s",
                DocumentNode::class,
                Utils::printSafe($source)
            ));
        }

        return $source;
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $params
     * @return array
     */
    public function resolveValidationRules(ServerConfig $config, OperationParams $params)
    {
        // Allow customizing validation rules per operation:
        $validationRules = $config->getValidationRules();

        if (is_callable($validationRules)) {
            $validationRules = $validationRules($params);

            if (!is_array($validationRules)) {
                throw new InvariantViolation(
                    "Validation rules callable must return array of rules, but got: %s" .
                    Utils::printSafe($validationRules)
                );
            }
        }

        return $validationRules;
    }

    /**
     * Parses HTTP request and returns GraphQL QueryParams contained in this request.
     * For batched requests it returns an array of QueryParams.
     *
     * This function doesn't check validity of these params.
     *
     * If $readRawBodyFn argument is not provided - will attempt to read raw request body from php://input stream
     *
     * @param callable|null $readRawBodyFn
     * @return OperationParams|OperationParams[]
     */
    public function parseHttpRequest(callable $readRawBodyFn = null)
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        if ($method === 'GET') {
            $request = array_change_key_case($_GET);

            if (isset($request['query']) || isset($request['queryid']) || isset($request['documentid'])) {
                $result = OperationParams::create($_GET, true);
            } else {
                throw new UserError('Cannot execute GET request without "query" or "queryId" parameter');
            }
        } else if ($method === 'POST') {
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;

            if (stripos($contentType, 'application/graphql') !== false) {
                $rawBody =  $readRawBodyFn ? $readRawBodyFn() : $this->readRawBody();
                $result = OperationParams::create(['query' => $rawBody ?: '']);
            } else if (stripos($contentType, 'application/json') !== false) {
                $rawBody = $readRawBodyFn ? $readRawBodyFn() : $this->readRawBody();
                $body = json_decode($rawBody ?: '', true);

                if (json_last_error()) {
                    throw new UserError("Could not parse JSON: " . json_last_error_msg());
                }
                if (!is_array($body)) {
                    throw new UserError(
                        "GraphQL Server expects JSON object or array, but got " .
                        Utils::printSafeJson($body)
                    );
                }
                if (isset($body[0])) {
                    $result = [];
                    foreach ($body as $index => $entry) {
                        $op = OperationParams::create($entry, true);
                        $result[] = $op;
                    }
                } else {
                    $result = OperationParams::create($body);
                }
            } else if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $result = OperationParams::create($_POST);
            } else if (null === $contentType) {
                throw new UserError('Missing "Content-Type" header');
            } else {
                throw new UserError("Unexpected content type: " . Utils::printSafeJson($contentType));
            }
        } else {
            throw new UserError('HTTP Method "' . $method . '" is not supported', 405);
        }
        return $result;
    }

    /**
     * @return bool|string
     */
    public function readRawBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * Checks validity of operation params and returns array of errors (empty array when params are valid)
     *
     * @param OperationParams $params
     * @return array
     */
    public function validateOperationParams(OperationParams $params)
    {
        $errors = [];
        if (!$params->query && !$params->queryId) {
            $errors[] = 'GraphQL Request must include at least one of those two parameters: "query" or "queryId"';
        }
        if ($params->query && $params->queryId) {
            $errors[] = 'GraphQL Request parameters "query" and "queryId" are mutually exclusive';
        }

        if ($params->query !== null && (!is_string($params->query) || empty($params->query))) {
            $errors[] = 'GraphQL Request parameter "query" must be string, but got ' .
                Utils::printSafeJson($params->query);
        }
        if ($params->queryId !== null && (!is_string($params->queryId) || empty($params->queryId))) {
            $errors[] = 'GraphQL Request parameter "queryId" must be string, but got ' .
                Utils::printSafeJson($params->queryId);
        }

        if ($params->operation !== null && (!is_string($params->operation) || empty($params->operation))) {
            $errors[] = 'GraphQL Request parameter "operation" must be string, but got ' .
                Utils::printSafeJson($params->operation);
        }
        if ($params->variables !== null && (!is_array($params->variables) || isset($params->variables[0]))) {
            $errors[] = 'GraphQL Request parameter "variables" must be object, but got ' .
                Utils::printSafeJson($params->variables);
        }
        return $errors;
    }

    /**
     * Assertion to check that parsed body is valid instance of OperationParams (or array of instances)
     *
     * @param OperationParams|OperationParams[] $parsedBody
     * @throws InvariantViolation
     * @throws UserError
     */
    public function assertValidRequest($parsedBody)
    {
        if (is_array($parsedBody)) {
            foreach ($parsedBody as $index => $entry) {
                if (!$entry instanceof OperationParams) {
                    throw new InvariantViolation(sprintf(
                        'GraphQL Server: Parsed http request must be an instance of %s or array of such instances, '.
                        'but got invalid array where entry at position %d is %s',
                        OperationParams::class,
                        $index,
                        Utils::printSafe($entry)
                    ));
                }

                $errors = $this->validateOperationParams($entry);

                if (!empty($errors[0])) {
                    $err = $index ? "Error in query #$index: {$errors[0]}" : $errors[0];
                    throw new UserError($err);
                }
            }
        } else if ($parsedBody instanceof OperationParams) {
            $errors = $this->validateOperationParams($parsedBody);
            if (!empty($errors[0])) {
                throw new UserError($errors[0]);
            }
        } else {
            throw new InvariantViolation(sprintf(
                'GraphQL Server: Parsed http request must be an instance of %s or array of such instances, but got %s',
                OperationParams::class,
                Utils::printSafe($parsedBody)
            ));
        }
    }
}
