<?php declare(strict_types=1);

namespace GraphQL\Language;

class Source
{
    public string $body;

    public int $length;

    public string $name;

    public SourceLocation $locationOffset;

    /**
     * A representation of source input to GraphQL.
     *
     * `name` and `locationOffset` are optional. They are useful for clients who
     * store GraphQL documents in source files; for example, if the GraphQL input
     * starts at line 40 in a file named Foo.graphql, it might be useful for name to
     * be "Foo.graphql" and location to be `{ line: 40, column: 0 }`.
     * line and column in locationOffset are 1-indexed
     */
    public function __construct(string $body, ?string $name = null, ?SourceLocation $location = null)
    {
        $this->body = $body;
        $this->length = mb_strlen($body, 'UTF-8');
        $this->name = $name === '' || $name === null
            ? 'GraphQL request'
            : $name;
        $this->locationOffset = $location ?? new SourceLocation(1, 1);
    }

    public function getLocation(int $position): SourceLocation
    {
        $utfChars = json_decode('"\u2028\u2029"');
        $lineRegexp = '/\r\n|[\n\r' . $utfChars . ']/su';
        $bodyBeforePosition = mb_substr($this->body, 0, $position, 'UTF-8');
        $lines = preg_split($lineRegexp, $bodyBeforePosition);
        assert(is_array($lines), 'the line regexp is statically known to be valid');

        $currentLine = end($lines);
        assert(is_string($currentLine), 'preg_split always returns at least one element');

        return new SourceLocation(count($lines), mb_strlen($currentLine, 'UTF-8') + 1);
    }
}
