<?php
namespace GraphQL\Server;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Contains functionality that could be re-used by various server implementations
 */
class Helper
{

    /**
     * Parses HTTP request using PHP globals and returns GraphQL OperationParams
     * contained in this request. For batched requests it returns an array of OperationParams.
     *
     * This function does not check validity of these params
     * (validation is performed separately in validateOperationParams() method).
     *
     * If $readRawBodyFn argument is not provided - will attempt to read raw request body
     * from `php://input` stream.
     *
     * Internally it normalizes input to $method, $bodyParams and $queryParams and
     * calls `parseRequestParams()` to produce actual return value.
     *
     * For PSR-7 request parsing use `parsePsrRequest()` instead.
     *
     * @api
     * @param callable|null $readRawBodyFn
     * @return OperationParams|OperationParams[]
     * @throws RequestError
     */
    public function parseHttpRequest(callable $readRawBodyFn = null)
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
        $bodyParams = [];
        $urlParams = $_GET;

        if ($method === 'POST') {
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;

            if (stripos($contentType, 'application/graphql') !== false) {
                $rawBody =  $readRawBodyFn ? $readRawBodyFn() : $this->readRawBody();
                $bodyParams = ['query' => $rawBody ?: ''];
            } else if (stripos($contentType, 'application/json') !== false) {
                $rawBody = $readRawBodyFn ? $readRawBodyFn() : $this->readRawBody();
                $bodyParams = json_decode($rawBody ?: '', true);

                if (json_last_error()) {
                    throw new RequestError("Could not parse JSON: " . json_last_error_msg());
                }
                if (!is_array($bodyParams)) {
                    throw new RequestError(
                        "GraphQL Server expects JSON object or array, but got " .
                        Utils::printSafeJson($bodyParams)
                    );
                }
            } else if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $bodyParams = $_POST;
            } else if (null === $contentType) {
                throw new RequestError('Missing "Content-Type" header');
            } else {
                throw new RequestError("Unexpected content type: " . Utils::printSafeJson($contentType));
            }
        }

        return $this->parseRequestParams($method, $bodyParams, $urlParams);
    }

    /**
     * Parses normalized request params and returns instance of OperationParams
     * or array of OperationParams in case of batch operation.
     *
     * Returned value is a suitable input for `executeOperation` or `executeBatch` (if array)
     *
     * @api
     * @param string $method
     * @param array $bodyParams
     * @param array $queryParams
     * @return OperationParams|OperationParams[]
     * @throws RequestError
     */
    public function parseRequestParams($method, array $bodyParams, array $queryParams)
    {
        if ($method === 'GET') {
            $result = OperationParams::create($queryParams, true);
        } else if ($method === 'POST') {
            if (isset($bodyParams[0])) {
                $result = [];
                foreach ($bodyParams as $index => $entry) {
                    $op = OperationParams::create($entry);
                    $result[] = $op;
                }
            } else {
                $result = OperationParams::create($bodyParams);
            }
        } else {
            throw new RequestError('HTTP Method "' . $method . '" is not supported');
        }
        return $result;
    }

    /**
     * Checks validity of OperationParams extracted from HTTP request and returns an array of errors
     * if params are invalid (or empty array when params are valid)
     *
     * @api
     * @param OperationParams $params
     * @return Error[]
     */
    public function validateOperationParams(OperationParams $params)
    {
        $errors = [];
        if (!$params->query && !$params->queryId) {
            $errors[] = new RequestError('GraphQL Request must include at least one of those two parameters: "query" or "queryId"');
        }
        if ($params->query && $params->queryId) {
            $errors[] = new RequestError('GraphQL Request parameters "query" and "queryId" are mutually exclusive');
        }

        if ($params->query !== null && (!is_string($params->query) || empty($params->query))) {
            $errors[] = new RequestError(
                'GraphQL Request parameter "query" must be string, but got ' .
                Utils::printSafeJson($params->query)
            );
        }
        if ($params->queryId !== null && (!is_string($params->queryId) || empty($params->queryId))) {
            $errors[] = new RequestError(
                'GraphQL Request parameter "queryId" must be string, but got ' .
                Utils::printSafeJson($params->queryId)
            );
        }

        if ($params->operation !== null && (!is_string($params->operation) || empty($params->operation))) {
            $errors[] = new RequestError(
                'GraphQL Request parameter "operation" must be string, but got ' .
                Utils::printSafeJson($params->operation)
            );
        }
        if ($params->variables !== null && (!is_array($params->variables) || isset($params->variables[0]))) {
            $errors[] = new RequestError(
                'GraphQL Request parameter "variables" must be object or JSON string parsed to object, but got ' .
                Utils::printSafeJson($params->getOriginalInput('variables'))
            );
        }
        return $errors;
    }

    /**
     * Executes GraphQL operation with given server configuration and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter)
     *
     * @api
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
     * @api
     * @param ServerConfig $config
     * @param OperationParams[] $operations
     * @return ExecutionResult[]|Promise
     */
    public function executeBatch(ServerConfig $config, array $operations)
    {
        $promiseAdapter = $config->getPromiseAdapter() ?: Executor::getPromiseAdapter();
        $result = [];

        foreach ($operations as $operation) {
            $result[] = $this->promiseToExecuteOperation($promiseAdapter, $config, $operation, true);
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
     * @param bool $isBatch
     * @return Promise
     */
    private function promiseToExecuteOperation(PromiseAdapter $promiseAdapter, ServerConfig $config, OperationParams $op, $isBatch = false)
    {
        try {
            if (!$config->getSchema()) {
                throw new InvariantViolation("Schema is required for the server");
            }
            if ($isBatch && !$config->getQueryBatching()) {
                throw new RequestError("Batched queries are not supported by this server");
            }

            $errors = $this->validateOperationParams($op);

            if (!empty($errors)) {
                $errors = Utils::map($errors, function(RequestError $err) {
                    return Error::createLocatedError($err, null, null);
                });
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, $errors)
                );
            }

            $doc = $op->queryId ? $this->loadPersistedQuery($config, $op) : $op->query;

            if (!$doc instanceof DocumentNode) {
                $doc = Parser::parse($doc);
            }

            $operationType = AST::getOperation($doc, $op->operation);
            if ($op->isReadOnly() && $operationType !== 'query') {
                throw new RequestError("GET supports only query operation");
            }

            $result = GraphQL::promiseToExecute(
                $promiseAdapter,
                $config->getSchema(),
                $doc,
                $this->resolveRootValue($config, $op, $doc, $operationType),
                $this->resolveContextValue($config, $op, $doc, $operationType),
                $op->variables,
                $op->operation,
                $config->getFieldResolver(),
                $this->resolveValidationRules($config, $op, $doc, $operationType)
            );

        } catch (RequestError $e) {
            $result = $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [Error::createLocatedError($e)])
            );
        } catch (Error $e) {
            $result = $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }

        $applyErrorHandling = function (ExecutionResult $result) use ($config) {
            if ($config->getErrorsHandler()) {
                $result->setErrorsHandler($config->getErrorsHandler());
            }
            if ($config->getErrorFormatter() || $config->getDebug()) {
                $result->setErrorFormatter(
                    FormattedError::prepareFormatter($config->getErrorFormatter(),
                    $config->getDebug())
                );
            }
            return $result;
        };

        return $result->then($applyErrorHandling);
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $op
     * @return mixed
     * @throws RequestError
     */
    private function loadPersistedQuery(ServerConfig $config, OperationParams $op)
    {
        // Load query if we got persisted query id:
        $loader = $config->getPersistentQueryLoader();

        if (!$loader) {
            throw new RequestError("Persisted queries are not supported by this server");
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
     * @param DocumentNode $doc
     * @param $operationType
     * @return array
     */
    private function resolveValidationRules(ServerConfig $config, OperationParams $params, DocumentNode $doc, $operationType)
    {
        // Allow customizing validation rules per operation:
        $validationRules = $config->getValidationRules();

        if (is_callable($validationRules)) {
            $validationRules = $validationRules($params, $doc, $operationType);

            if (!is_array($validationRules)) {
                throw new InvariantViolation(sprintf(
                    "Expecting validation rules to be array or callable returning array, but got: %s",
                    Utils::printSafe($validationRules)
                ));
            }
        }

        return $validationRules;
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $params
     * @param DocumentNode $doc
     * @param $operationType
     * @return mixed
     */
    private function resolveRootValue(ServerConfig $config, OperationParams $params, DocumentNode $doc, $operationType)
    {
        $root = $config->getRootValue();

        if ($root instanceof \Closure) {
            $root = $root($params, $doc, $operationType);
        }

        return $root;
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $params
     * @param DocumentNode $doc
     * @param $operationType
     * @return mixed
     */
    private function resolveContextValue(ServerConfig $config, OperationParams $params, DocumentNode $doc, $operationType)
    {
        $context = $config->getContext();

        if ($context instanceof \Closure) {
            $context = $context($params, $doc, $operationType);
        }

        return $context;
    }

    /**
     * Send response using standard PHP `header()` and `echo`.
     *
     * @api
     * @param Promise|ExecutionResult|ExecutionResult[] $result
     * @param bool $exitWhenDone
     */
    public function sendResponse($result, $exitWhenDone = false)
    {
        if ($result instanceof Promise) {
            $result->then(function($actualResult) use ($exitWhenDone) {
                $this->doSendResponse($actualResult, $exitWhenDone);
            });
        } else {
            $this->doSendResponse($result, $exitWhenDone);
        }
    }

    /**
     * @param $result
     * @param $exitWhenDone
     */
    private function doSendResponse($result, $exitWhenDone)
    {
        $httpStatus = $this->resolveHttpStatus($result);
        $this->emitResponse($result, $httpStatus, $exitWhenDone);
    }

    /**
     * @param array|\JsonSerializable $jsonSerializable
     * @param int $httpStatus
     * @param bool $exitWhenDone
     */
    public function emitResponse($jsonSerializable, $httpStatus, $exitWhenDone)
    {
        $body = json_encode($jsonSerializable);
        header('Content-Type: application/json', true, $httpStatus);
        echo $body;

        if ($exitWhenDone) {
            exit;
        }
    }

    /**
     * @return bool|string
     */
    private function readRawBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * @param $result
     * @return int
     */
    private function resolveHttpStatus($result)
    {
        if (is_array($result) && isset($result[0])) {
            Utils::each($result, function ($executionResult, $index) {
                if (!$executionResult instanceof ExecutionResult) {
                    throw new InvariantViolation(sprintf(
                        "Expecting every entry of batched query result to be instance of %s but entry at position %d is %s",
                        ExecutionResult::class,
                        $index,
                        Utils::printSafe($executionResult)
                    ));
                }
            });
            $httpStatus = 200;
        } else {
            if (!$result instanceof ExecutionResult) {
                throw new InvariantViolation(sprintf(
                    "Expecting query result to be instance of %s but got %s",
                    ExecutionResult::class,
                    Utils::printSafe($result)
                ));
            }
            if ($result->data === null && !empty($result->errors)) {
                $httpStatus = 400;
            } else {
                $httpStatus = 200;
            }
        }
        return $httpStatus;
    }

    /**
     * Converts PSR-7 request to OperationParams[]
     *
     * @api
     * @param ServerRequestInterface $request
     * @return array|Helper
     * @throws RequestError
     */
    public function parsePsrRequest(ServerRequestInterface $request)
    {
        if ($request->getMethod() === 'GET') {
            $bodyParams = [];
        } else {
            $contentType = $request->getHeader('content-type');

            if (!isset($contentType[0])) {
                throw new RequestError('Missing "Content-Type" header');
            }

            if (stripos('application/graphql', $contentType[0]) !== false) {
                $bodyParams = ['query' => $request->getBody()->getContents()];
            } else if (stripos('application/json', $contentType[0]) !== false) {
                $bodyParams = $request->getParsedBody();

                if (null === $bodyParams) {
                    throw new InvariantViolation(
                        "PSR-7 request is expected to provide parsed body for \"application/json\" requests but got null"
                    );
                }

                if (!is_array($bodyParams)) {
                    throw new RequestError(
                        "GraphQL Server expects JSON object or array, but got " .
                        Utils::printSafeJson($bodyParams)
                    );
                }
            } else {
                $bodyParams = $request->getParsedBody();

                if (!is_array($bodyParams)) {
                    throw new RequestError("Unexpected content type: " . Utils::printSafeJson($contentType[0]));
                }
            }
        }

        return $this->parseRequestParams(
            $request->getMethod(),
            $bodyParams,
            $request->getQueryParams()
        );
    }

    /**
     * Converts query execution result to PSR-7 response
     *
     * @api
     * @param Promise|ExecutionResult|ExecutionResult[] $result
     * @param ResponseInterface $response
     * @param StreamInterface $writableBodyStream
     * @return Promise|ResponseInterface
     */
    public function toPsrResponse($result, ResponseInterface $response, StreamInterface $writableBodyStream)
    {
        if ($result instanceof Promise) {
            return $result->then(function($actualResult) use ($response, $writableBodyStream) {
                return $this->doConvertToPsrResponse($actualResult, $response, $writableBodyStream);
            });
        } else {
            return $this->doConvertToPsrResponse($result, $response, $writableBodyStream);
        }
    }

    private function doConvertToPsrResponse($result, ResponseInterface $response, StreamInterface $writableBodyStream)
    {
        $httpStatus = $this->resolveHttpStatus($result);

        $result = json_encode($result);
        $writableBodyStream->write($result);

        return $response
            ->withStatus($httpStatus)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($writableBodyStream);
    }
}
