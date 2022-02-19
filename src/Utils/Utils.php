<?php declare(strict_types=1);

namespace GraphQL\Utils;

use function array_keys;
use function array_map;
use function array_reduce;
use function array_shift;
use function array_slice;
use function count;
use function dechex;
use function func_get_args;
use function func_num_args;
use function get_class;
use function gettype;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Type;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function json_encode;
use function mb_convert_encoding;
use function mb_strlen;
use function mb_substr;
use function method_exists;
use function ord;
use function pack;
use function preg_match;
use function property_exists;
use function range;
use function sprintf;
use stdClass;
use function unpack;

class Utils
{
    public static function undefined(): stdClass
    {
        static $undefined;

        return $undefined ??= new stdClass();
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function assign(object $obj, array $vars): object
    {
        foreach ($vars as $key => $value) {
            if (! property_exists($obj, $key)) {
                $cls = get_class($obj);
                Warning::warn(
                    "Trying to set non-existing property '{$key}' on class '{$cls}'",
                    Warning::WARNING_ASSIGN
                );
            }

            $obj->{$key} = $value;
        }

        return $obj;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<mixed> $iterable
     * @phpstan-param iterable<TKey, TValue> $iterable
     * @phpstan-param callable(TValue, TKey): bool $predicate
     *
     * @return mixed
     * @phpstan-return TValue|null
     */
    public static function find(iterable $iterable, callable $predicate)
    {
        foreach ($iterable as $key => $value) {
            if ($predicate($value, $key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param iterable<mixed> $iterable
     */
    public static function every($iterable, callable $predicate): bool
    {
        foreach ($iterable as $key => $value) {
            if (! $predicate($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed  $test    will be evaluated for truthy-ness
     * @param string $message
     *
     * @throws InvariantViolation
     */
    public static function invariant($test, $message = ''): void
    {
        // @phpstan-ignore-next-line we want to evaluate for truthy-ness
        if ($test) {
            return;
        }

        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            $message = sprintf(...$args);
        }

        // TODO switch to Error here
        throw new InvariantViolation($message);
    }

    /**
     * @param mixed $var
     */
    public static function printSafeJson($var): string
    {
        if ($var instanceof stdClass) {
            return json_encode($var, JSON_THROW_ON_ERROR);
        }

        if (is_array($var)) {
            return json_encode($var, JSON_THROW_ON_ERROR);
        }

        if ($var === '') {
            return '(empty string)';
        }

        if ($var === null) {
            return 'null';
        }

        if ($var === false) {
            return 'false';
        }

        if ($var === true) {
            return 'true';
        }

        if (is_string($var)) {
            return "\"{$var}\"";
        }

        if (is_scalar($var)) {
            return (string) $var;
        }

        return gettype($var);
    }

    /**
     * @param Type|mixed $var
     */
    public static function printSafe($var): string
    {
        if ($var instanceof Type) {
            return $var->toString();
        }

        if (is_object($var)) {
            if (method_exists($var, '__toString')) {
                return (string) $var;
            }

            return 'instance of ' . get_class($var);
        }

        if (is_array($var)) {
            return json_encode($var, JSON_THROW_ON_ERROR);
        }

        if ($var === '') {
            return '(empty string)';
        }

        if ($var === null) {
            return 'null';
        }

        if ($var === false) {
            return 'false';
        }

        if ($var === true) {
            return 'true';
        }

        if (is_string($var)) {
            return $var;
        }

        if (is_scalar($var)) {
            return (string) $var;
        }

        return gettype($var);
    }

    /**
     * UTF-8 compatible chr().
     */
    public static function chr(int $ord, string $encoding = 'UTF-8'): string
    {
        if ($encoding === 'UCS-4BE') {
            return pack('N', $ord);
        }

        return mb_convert_encoding(self::chr($ord, 'UCS-4BE'), $encoding, 'UCS-4BE');
    }

    /**
     * UTF-8 compatible ord().
     */
    public static function ord(string $char, string $encoding = 'UTF-8'): int
    {
        if (! isset($char[1])) {
            return ord($char);
        }

        if ($encoding !== 'UCS-4BE') {
            $char = mb_convert_encoding($char, 'UCS-4BE', $encoding);
        }

        // @phpstan-ignore-next-line format string is statically known to be correct
        return unpack('N', $char)[1];
    }

    /**
     * Returns UTF-8 char code at given $positing of the $string.
     */
    public static function charCodeAt(string $string, int $position): int
    {
        $char = mb_substr($string, $position, 1, 'UTF-8');

        return self::ord($char);
    }

    public static function printCharCode(?int $code): string
    {
        if ($code === null) {
            return '<EOF>';
        }

        return $code < 0x007F
            // Trust JSON for ASCII
            ? json_encode(self::chr($code), JSON_THROW_ON_ERROR)
            // Otherwise, print the escaped form
            : '"\\u' . dechex($code) . '"';
    }

    /**
     * Upholds the spec rules about naming.
     *
     * @throws Error
     */
    public static function assertValidName(string $name): void
    {
        $error = self::isValidNameError($name);
        if ($error !== null) {
            throw $error;
        }
    }

    /**
     * Returns an Error if a name is invalid.
     */
    public static function isValidNameError(string $name, ?Node $node = null): ?Error
    {
        if (isset($name[1]) && $name[0] === '_' && $name[1] === '_') {
            return new Error(
                'Name "' . $name . '" must not begin with "__", which is reserved by GraphQL introspection.',
                $node
            );
        }

        if (preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/', $name) !== 1) {
            return new Error(
                'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "' . $name . '" does not.',
                $node
            );
        }

        return null;
    }

    /**
     * @param array<string> $items
     */
    public static function quotedOrList(array $items): string
    {
        $quoted = array_map(
            static fn (string $item): string => "\"{$item}\"",
            $items
        );

        return self::orList($quoted);
    }

    /**
     * @param array<string> $items
     */
    public static function orList(array $items): string
    {
        if (count($items) === 0) {
            return '';
        }

        $selected = array_slice($items, 0, 5);
        $selectedLength = count($selected);
        $firstSelected = $selected[0];

        if ($selectedLength === 1) {
            return $firstSelected;
        }

        return array_reduce(
            range(1, $selectedLength - 1),
            static function ($list, $index) use ($selected, $selectedLength): string {
                return $list
                    . ($selectedLength > 2 ? ', ' : ' ')
                    . ($index === $selectedLength - 1 ? 'or ' : '')
                    . $selected[$index];
            },
            $firstSelected
        );
    }

    /**
     * Given an invalid input string and a list of valid options, returns a filtered
     * list of valid options sorted based on their similarity with the input.
     *
     * @param array<string> $options
     *
     * @return array<int, string>
     */
    public static function suggestionList(string $input, array $options): array
    {
        $optionsByDistance = [];
        $lexicalDistance = new LexicalDistance($input);
        $threshold = mb_strlen($input) * 0.4 + 1;
        foreach ($options as $option) {
            $distance = $lexicalDistance->measure($option, $threshold);

            if ($distance !== null) {
                $optionsByDistance[$option] = $distance;
            }
        }

        \uksort($optionsByDistance, static function (string $a, string $b) use ($optionsByDistance) {
            $distanceDiff = $optionsByDistance[$a] - $optionsByDistance[$b];

            return $distanceDiff !== 0 ? $distanceDiff : \strnatcmp($a, $b);
        });

        return array_keys($optionsByDistance);
    }
}
