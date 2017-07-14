<?php
namespace GraphQL\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;

/**
 * Class StandardServer
 *
 * GraphQL server compatible with both:
 * https://github.com/graphql/express-graphql and https://github.com/apollographql/graphql-server
 *
 * @package GraphQL\Server
 */
class StandardServer
{
    /**
     * Creates new server
     *
     * @param ServerConfig $config
     * @return static
     */
    public static function create(ServerConfig $config)
    {
        return new static($config);
    }

    /**
     * @var ServerConfig
     */
    private $config;

    /**
     * StandardServer constructor.
     * @param ServerConfig $config
     */
    protected function __construct(ServerConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param OperationParams|OperationParams[] $parsedBody
     * @return ExecutionResult|ExecutionResult[]|Promise
     */
    public function executeRequest($parsedBody = null)
    {
        if (null !== $parsedBody) {
            $this->assertBodyIsParsedProperly(__METHOD__, $parsedBody);
        } else {
            $parsedBody = Helper::parseHttpRequest();
        }

        $batched = is_array($parsedBody);

        $result = [];
        foreach ((array) $parsedBody as $index => $operationParams) {
            $result[] = Helper::executeOperation($this->config, $operationParams);
        }

        return $batched ? $result : $result[0];
    }

    /**
     * @param $method
     * @param $parsedBody
     */
    private function assertBodyIsParsedProperly($method, $parsedBody)
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
