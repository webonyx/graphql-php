<?php
namespace GraphQL\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use Psr\Http\Message\ServerRequestInterface;

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
     * Executes GraphQL operation with given server configuration and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter)
     *
     * @param ServerRequestInterface $request
     * @return ExecutionResult|ExecutionResult[]|Promise
     */
    public function executePsrRequest(ServerRequestInterface $request)
    {
        $parsedBody = $this->helper->parsePsrRequest($request);
        return $this->executeRequest($parsedBody);
    }

    /**
     * Executes GraphQL operation with given server configuration and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter)
     *
     * @param OperationParams|OperationParams[] $parsedBody
     * @return ExecutionResult|ExecutionResult[]|Promise
     * @throws InvariantViolation
     */
    public function executeRequest($parsedBody = null)
    {
        if (null !== $parsedBody) {
            $parsedBody = $this->helper->parseHttpRequest();
        }

        if (is_array($parsedBody)) {
            return $this->helper->executeBatch($this->config, $parsedBody);
        } else {
            return $this->helper->executeOperation($this->config, $parsedBody);
        }
    }
}
