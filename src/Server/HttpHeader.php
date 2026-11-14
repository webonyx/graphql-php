<?php declare(strict_types=1);

namespace GraphQL\Server;

/**
 * @internal
 */
final class HttpHeader
{
    /** @return list<string> */
    public static function split(string $value, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $quoted = false;
        $length = strlen($value);
        for ($index = 0; $index < $length; ++$index) {
            $character = $value[$index];
            if ($quoted && $character === '\\') {
                ++$index;
            } elseif ($character === '"') {
                $quoted = ! $quoted;
            } elseif (! $quoted && $character === $delimiter) {
                $parts[] = substr($value, $start, $index - $start);
                $start = $index + 1;
            }
        }

        $parts[] = substr($value, $start);

        return $parts;
    }

    /** Decode HTTP quoted-pairs, rejecting malformed quoted strings. */
    public static function decodeParameterValue(string $value): ?string
    {
        if ($value === '' || $value[0] !== '"') {
            return $value;
        }

        $length = strlen($value);
        if ($length < 2 || $value[$length - 1] !== '"') {
            return null;
        }

        $decoded = '';
        for ($index = 1; $index < $length - 1; ++$index) {
            $character = $value[$index];
            if ($character === '\\') {
                ++$index;
                if ($index >= $length - 1) {
                    return null;
                }

                $character = $value[$index];
            } elseif ($character === '"') {
                return null;
            }

            $code = ord($character);
            if (($code < 32 && $character !== "\t") || $code === 127) {
                return null;
            }

            $decoded .= $character;
        }

        return $decoded;
    }
}
