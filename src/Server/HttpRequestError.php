<?php declare(strict_types=1);

namespace GraphQL\Server;

/**
 * An HTTP request parsing error with its response status and headers.
 *
 * @internal
 */
final class HttpRequestError extends RequestError
{
    public int $statusCode;

    /** @var array<string, string> */
    public array $headers;

    /** @param array<string, string> $headers */
    public function __construct(string $message, int $statusCode, array $headers = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
}
