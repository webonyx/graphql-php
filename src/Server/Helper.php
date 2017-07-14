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
    public static function executeOperation(ServerConfig $config, OperationParams $op)
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
    private static function loadPersistedQuery(ServerConfig $config, OperationParams $op)
    {
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
    private static function resolveValidationRules(ServerConfig $config, OperationParams $params)
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
    public static function parseHttpRequest()
    {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;

        $assertValid = function (OperationParams $opParams, $queryNum = null) {
            $errors = $opParams->validate();
            if (!empty($errors[0])) {
                $err = $queryNum ? "Error in query #$queryNum: {$errors[0]}" : $errors[0];
                throw new UserError($err);
            }
        };

        if (stripos($contentType, 'application/graphql' !== false)) {
            $body = file_get_contents('php://input') ?: '';
            $op = OperationParams::create(['query' => $body]);
            $assertValid($op);
        } else if (stripos($contentType, 'application/json') !== false || stripos($contentType, 'text/json') !== false) {
            $body = file_get_contents('php://input') ?: '';
            $data = json_decode($body, true);

            if (json_last_error()) {
                throw new UserError("Could not parse JSON: " . json_last_error_msg());
            }
            if (!is_array($data)) {
                throw new UserError(
                    "GraphQL Server expects JSON object or array, but got %s" .
                    Utils::printSafe($data)
                );
            }
            if (isset($data[0])) {
                $op = [];
                foreach ($data as $index => $entry) {
                    $params = OperationParams::create($entry);
                    $assertValid($params, $index);
                    $op[] = $params;
                }
            } else {
                $op = OperationParams::create($data);
                $assertValid($op);
            }
        } else if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $op = OperationParams::create($_GET, false);
            } else {
                $op = OperationParams::create($_POST);
            }
            $assertValid($op);
        } else {
            throw new UserError("Bad request: unexpected content type: " . Utils::printSafe($contentType));
        }

        return $op;
    }
}
