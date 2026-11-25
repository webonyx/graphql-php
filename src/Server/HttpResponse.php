<?php declare(strict_types=1);

namespace GraphQL\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class HttpResponse
{
    public int $statusCode;

    /** @var array<string, string> */
    public array $headers;

    /** @var array<mixed> */
    public array $body;

    /**
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(array $body, int $statusCode, string $mediaType, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers + [
            'Content-Type' => "{$mediaType}; charset=utf-8",
            'Vary' => 'Accept',
        ];
    }

    /** @throws \JsonException */
    public function send(): void
    {
        // Encode before sending headers so an encoding failure cannot look successful.
        $json = json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", $name !== 'Vary');
        }

        echo $json;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function toPsrResponse(ResponseInterface $response, StreamInterface $stream): ResponseInterface
    {
        $stream->write(json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $response = $response->withStatus($this->statusCode)->withBody($stream);
        foreach ($this->headers as $name => $value) {
            if ($name === 'Vary') {
                $vary = array_map('trim', explode(',', strtolower($response->getHeaderLine('Vary'))));
                if (! in_array('*', $vary, true) && ! in_array('accept', $vary, true)) {
                    $response = $response->withAddedHeader($name, $value);
                }
            } else {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
