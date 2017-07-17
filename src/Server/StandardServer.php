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
     * @var Helper
     */
    private $helper;

    /**
     * StandardServer constructor.
     * @param ServerConfig $config
     */
    protected function __construct(ServerConfig $config)
    {
        $this->config = $config;
        $this->helper = new Helper();
    }

    /**
     * @param OperationParams|OperationParams[] $parsedBody
     * @return ExecutionResult|ExecutionResult[]|Promise
     */
    public function executeRequest($parsedBody = null)
    {
        if (null !== $parsedBody) {
            $parsedBody = $this->helper->parseHttpRequest();
        }
        $this->helper->assertValidRequest($parsedBody);

        $batched = is_array($parsedBody);

        $result = [];
        foreach ((array) $parsedBody as $index => $operationParams) {
            $result[] = $this->helper->executeOperation($this->config, $operationParams);
        }

        return $batched ? $result : $result[0];
    }
}
