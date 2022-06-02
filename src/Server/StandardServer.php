<?php

declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Utils\Utils;
use function is_array;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * GraphQL server compatible with both: [express-graphql](https://github.com/graphql/express-graphql)
 * and [Apollo Server](https://github.com/apollographql/graphql-server).
 * Usage Example:.
 *
 *     $server = new StandardServer([
 *       'schema' => $mySchema
 *     ]);
 *     $server->handleRequest();
 *
 * Or using [ServerConfig](class-reference.md#graphqlserverserverconfig) instance:
 *
 *     $config = GraphQL\Server\ServerConfig::create()
 *         ->setSchema($mySchema)
 *         ->setContext($myContext);
 *
 *     $server = new GraphQL\Server\StandardServer($config);
 *     $server->handleRequest();
 *
 * See [dedicated section in docs](executing-queries.md#using-server) for details.
 */
class StandardServer
{
    private ServerConfig $config;

    private Helper $helper;

    /**
     * Converts and exception to error and sends spec-compliant HTTP 500 error.
     * Useful when an exception is thrown somewhere outside of server execution context
     * (e.g. during schema instantiation).
     *
     * @api
     */
    public static function send500Error(Throwable $error, int $debug = DebugFlag::NONE, bool $exitWhenDone = false): void
    {
        $response = [
            'errors' => [FormattedError::createFromException($error, $debug)],
        ];
        $helper = new Helper();
        $helper->emitResponse($response, 500, $exitWhenDone);
    }

    /**
     * Creates new instance of a standard GraphQL HTTP server.
     *
     * @param ServerConfig|array<string, mixed> $config
     *
     * @api
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            $config = ServerConfig::create($config);
        }

        // @phpstan-ignore-next-line necessary until we can use proper union types
        if (! $config instanceof ServerConfig) {
            throw new InvariantViolation('Expecting valid server config, but got ' . Utils::printSafe($config));
        }

        $this->config = $config;
        $this->helper = new Helper();
    }

    /**
     * Parses HTTP request, executes and emits response (using standard PHP `header` function and `echo`).
     *
     * By default (when $parsedBody is not set) it uses PHP globals to parse a request.
     * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
     * and then pass it to the server.
     *
     * See `executeRequest()` if you prefer to emit response yourself
     * (e.g. using Response object of some framework)
     *
     * @param OperationParams|array<OperationParams> $parsedBody
     *
     * @api
     */
    public function handleRequest($parsedBody = null, bool $exitWhenDone = false): void
    {
        $result = $this->executeRequest($parsedBody);
        $this->helper->sendResponse($result, $exitWhenDone);
    }

    /**
     * Executes GraphQL operation and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter).
     *
     * By default (when $parsedBody is not set) it uses PHP globals to parse a request.
     * It is possible to implement request parsing elsewhere (e.g. using framework Request instance)
     * and then pass it to the server.
     *
     * PSR-7 compatible method executePsrRequest() does exactly this.
     *
     * @param OperationParams|array<OperationParams> $parsedBody
     *
     * @throws InvariantViolation
     *
     * @return ExecutionResult|array<int, ExecutionResult>|Promise
     *
     * @api
     */
    public function executeRequest($parsedBody = null)
    {
        if (null === $parsedBody) {
            $parsedBody = $this->helper->parseHttpRequest();
        }

        if (is_array($parsedBody)) {
            return $this->helper->executeBatch($this->config, $parsedBody);
        }

        return $this->helper->executeOperation($this->config, $parsedBody);
    }

    /**
     * Executes PSR-7 request and fulfills PSR-7 response.
     *
     * See `executePsrRequest()` if you prefer to create response yourself
     * (e.g. using specific JsonResponse instance of some framework).
     *
     * @return ResponseInterface|Promise
     *
     * @api
     */
    public function processPsrRequest(
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $writableBodyStream
    ) {
        $result = $this->executePsrRequest($request);

        return $this->helper->toPsrResponse($result, $response, $writableBodyStream);
    }

    /**
     * Executes GraphQL operation and returns execution result
     * (or promise when promise adapter is different from SyncPromiseAdapter).
     *
     * @return ExecutionResult|array<int, ExecutionResult>|Promise
     *
     * @api
     */
    public function executePsrRequest(RequestInterface $request)
    {
        $parsedBody = $this->helper->parsePsrRequest($request);

        return $this->executeRequest($parsedBody);
    }

    /**
     * Returns an instance of Server helper, which contains most of the actual logic for
     * parsing / validating / executing request (which could be re-used by other server implementations).
     *
     * @api
     */
    public function getHelper(): Helper
    {
        return $this->helper;
    }
}
