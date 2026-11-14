<?php declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Error\SyntaxError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Exception\CannotParseJsonBody;
use GraphQL\Server\Exception\CannotReadBody;
use GraphQL\Server\Exception\GetMethodSupportsOnlyQueryOperation;
use GraphQL\Server\Exception\HttpMethodNotSupported;
use GraphQL\Server\Exception\MissingContentTypeHeader;
use GraphQL\Server\Exception\UnexpectedContentType;

/**
 * @internal
 */
final class HttpHelper
{
    /**
     * Select by quality, with a specific range taking precedence over a wildcard.
     * Equal preferences use the client's order, then prefer the GraphQL media type.
     */
    public function negotiate(string $accept): ?string
    {
        if (trim($accept) === '') {
            return 'application/graphql-response+json';
        }

        $selected = null;
        $selectedQuality = 0.0;
        $selectedOrder = PHP_INT_MAX;
        foreach (['application/graphql-response+json', 'application/json'] as $mediaType) {
            $quality = 0.0;
            $specificity = -1;
            $order = PHP_INT_MAX;
            foreach (HttpHeader::split(strtolower($accept), ',') as $index => $range) {
                $parts = array_map('trim', HttpHeader::split($range, ';'));
                $type = array_shift($parts);
                if ($type !== $mediaType && $type !== 'application/*' && $type !== '*/*') {
                    continue;
                }

                $rangeSpecificity = $type === $mediaType ? 4 : ($type === 'application/*' ? 2 : 0);
                $rangeQuality = 1.0;
                foreach ($parts as $parameter) {
                    $pair = array_map('trim', explode('=', $parameter, 2));
                    if (count($pair) !== 2) {
                        continue 2;
                    }

                    [$name, $value] = $pair;
                    if ($name === 'q') {
                        if (preg_match('~^(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)$~D', $value) !== 1) {
                            continue 2;
                        }

                        $rangeQuality = (float) $value;
                    } elseif ($name === 'charset' && HttpHeader::decodeParameterValue($value) === 'utf-8') {
                        $rangeSpecificity |= 1;
                    } else {
                        // We only produce UTF-8, without other media type parameters.
                        continue 2;
                    }
                }

                if ($rangeSpecificity > $specificity || ($rangeSpecificity === $specificity && $rangeQuality > $quality)) {
                    $specificity = $rangeSpecificity;
                    $quality = $rangeQuality;
                    $order = $index;
                }
            }

            if ($quality > $selectedQuality || ($quality > 0 && $quality === $selectedQuality && $order < $selectedOrder)) {
                $selected = $mediaType;
                $selectedQuality = $quality;
                $selectedOrder = $order;
            }
        }

        return $selected;
    }

    /** @param ExecutionResult|array<ExecutionResult> $result */
    public function createResponse($result, string $mediaType): HttpResponse
    {
        if (is_array($result)) {
            $body = array_map(fn (ExecutionResult $operation) => $this->createResponse($operation, $mediaType)->body, $result);

            return new HttpResponse($body, 200, 'application/json');
        }

        $body = $result->toArray();
        $hasData = $result->executed || $result->data !== null;
        if ($hasData) {
            // A null execution result must not look like a pre-execution request error.
            $body['data'] = $result->data === [] ? new \stdClass() : $result->data;
        }

        // A custom error handler may filter all errors. Keep the HTTP result valid
        // without exposing the unformatted errors or changing legacy serialization.
        if ((! $hasData || $result->data === null) && ! isset($body['errors'])) {
            $body['errors'] = [['message' => 'GraphQL request failed.']];
        }

        $statusCode = 200;
        $headers = [];
        if (! $hasData) {
            $statusCode = 422;
            foreach ($result->errors as $error) {
                $cause = $error->getPrevious();
                if ($cause instanceof HttpRequestError) {
                    $statusCode = $cause->statusCode;
                    $headers = $cause->headers;
                    break;
                }

                if ($error instanceof SyntaxError || $cause instanceof CannotParseJsonBody) {
                    $statusCode = 400;
                    break;
                }

                if ($cause instanceof GetMethodSupportsOnlyQueryOperation) {
                    $statusCode = 405;
                    $headers['Allow'] = 'POST';
                    break;
                }

                if ($cause instanceof HttpMethodNotSupported) {
                    $statusCode = 405;
                    $headers['Allow'] = 'GET, POST';
                    break;
                }

                if ($cause instanceof MissingContentTypeHeader || $cause instanceof UnexpectedContentType) {
                    $statusCode = 415;
                    break;
                }

                if ($cause instanceof CannotReadBody) {
                    $statusCode = 500;
                    break;
                }
            }
        }

        // Follow the specification's legacy-client recommendation: only successful
        // responses substitute application/json for the GraphQL response media type.
        if ($statusCode >= 400) {
            $mediaType = 'application/graphql-response+json';
        }

        return new HttpResponse($body, $statusCode, $mediaType, $headers);
    }
}
