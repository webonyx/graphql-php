<?php declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Executor\ExecutionResult;

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
        $body = $result instanceof ExecutionResult
            ? $result->toArray()
            : array_map(static fn (ExecutionResult $operation) => $operation->toArray(), $result);

        return new HttpResponse($body, 200, $mediaType);
    }
}
