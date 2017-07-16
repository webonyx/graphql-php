<?php
namespace GraphQL\Server;

use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;

/**
 * Class Helper
 * Contains functionality that could be re-used by various server implementations
 *
 * @package GraphQL\Server
 */
class Helper
{
    /**
     * Executes GraphQL operation with given server configuration and returns execution result (or promise)
     *
     * @param ServerConfig $config
     * @param OperationParams $op
     *
     * @return ExecutionResult|Promise
     */
    public function executeOperation(ServerConfig $config, OperationParams $op)
    {
        $phpErrors = [];
        $execute = function() use ($config, $op) {
            $doc = $op->queryId ? static::loadPersistedQuery($config, $op) : $op->query;

            if (!$doc instanceof DocumentNode) {
                $doc = Parser::parse($doc);
            }
            if (!$op->allowsMutation() && AST::isMutation($op->operation, $doc)) {
                throw new UserError("Cannot execute mutation in read-only context");
            }

            return GraphQL::executeAndReturnResult(
                $config->getSchema(),
                $doc,
                $config->getRootValue(),
                $config->getContext(),
                $op->variables,
                $op->operation,
                $config->getDefaultFieldResolver(),
                static::resolveValidationRules($config, $op),
                $config->getPromiseAdapter()
            );
        };
        if ($config->getDebug()) {
            $execute = Utils::withErrorHandling($execute, $phpErrors);
        }
        $result = $execute();

        $applyErrorFormatting = function(ExecutionResult $result) use ($config, $phpErrors) {
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

        return $result instanceof Promise ?
            $result->then($applyErrorFormatting) :
            $applyErrorFormatting($result);
    }

    /**
     * @param ServerConfig $config
     * @param OperationParams $op
     * @return string|DocumentNode
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
     * @return OperationParams|OperationParams[]
     */
    public function parseHttpRequest()
    {
        list ($parsedBody, $isReadonly) = $this->parseRawBody();
        return $this->toOperationParams($parsedBody, $isReadonly);
    }

    /**
     * Extracts parsed body and readonly flag from HTTP request
     *
     * If $readRawBodyFn argument is not provided - will attempt to read raw request body from php://input stream
     *
     * @param callable|null $readRawBodyFn
     * @return array
     */
    public function parseRawBody(callable $readRawBodyFn = null)
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        if ($method === 'GET') {
            $isReadonly = true;
            $request = array_change_key_case($_GET);

            if (isset($request['query']) || isset($request['queryid']) || isset($request['documentid'])) {
                $body = $_GET;
            } else {
                throw new UserError('Cannot execute GET request without "query" or "queryId" parameter');
            }
        } else if ($method === 'POST') {
            $isReadonly = false;
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;

            if (stripos($contentType, 'application/graphql') !== false) {
                $rawBody =  $readRawBodyFn ? $readRawBodyFn() : $this->readRawBody();
                $body = ['query' => $rawBody ?: ''];
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
            } else if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $body = $_POST;
            } else if (null === $contentType) {
                throw new UserError('Missing "Content-Type" header');
            } else {
                throw new UserError("Unexpected content type: " . Utils::printSafeJson($contentType));
            }
        } else {
            throw new UserError('HTTP Method "' . $method . '" is not supported', 405);
        }
        return [
            $body,
            $isReadonly
        ];
    }

    /**
     * Converts parsed body to OperationParams (or list of OperationParams for batched request)
     *
     * @param $parsedBody
     * @param $isReadonly
     * @return OperationParams|OperationParams[]
     */
    public function toOperationParams($parsedBody, $isReadonly)
    {
        $assertValid = function (OperationParams $opParams, $queryNum = null) {
            $errors = $opParams->validate();
            if (!empty($errors[0])) {
                $err = $queryNum ? "Error in query #$queryNum: {$errors[0]}" : $errors[0];
                throw new UserError($err);
            }
        };

        if (isset($parsedBody[0])) {
            // Batched query
            $result = [];
            foreach ($parsedBody as $index => $entry) {
                $op = OperationParams::create($entry, $isReadonly);
                $assertValid($op, $index);
                $result[] = $op;
            }
        } else {
            $result = OperationParams::create($parsedBody, $isReadonly);
            $assertValid($result);
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
     * Assertion to check that parsed body is valid instance of OperationParams (or array of instances)
     *
     * @param $method
     * @param $parsedBody
     */
    public function assertBodyIsParsedProperly($method, $parsedBody)
    {
        if (is_array($parsedBody)) {
            foreach ($parsedBody as $index => $entry) {
                if (!$entry instanceof OperationParams) {
                    throw new InvariantViolation(sprintf(
                        '%s expects instance of %s or array of instances. Got invalid array where entry at position %d is %s',
                        $method,
                        OperationParams::class,
                        $index,
                        Utils::printSafe($entry)
                    ));
                }
                $errors = $entry->validate();

                if (!empty($errors[0])) {
                    $err = $index ? "Error in query #$index: {$errors[0]}" : $errors[0];
                    throw new InvariantViolation($err);
                }
            }
        }

        if ($parsedBody instanceof OperationParams) {
            $errors = $parsedBody->validate();
            if (!empty($errors[0])) {
                throw new InvariantViolation($errors[0]);
            }
        }

        throw new InvariantViolation(sprintf(
            '%s expects instance of %s or array of instances, but got %s',
            $method,
            OperationParams::class,
            Utils::printSafe($parsedBody)
        ));
    }
}
