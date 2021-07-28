<?php

declare(strict_types=1);

namespace GraphQL\Error;

use Exception;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use JsonSerializable;
use Throwable;
use Traversable;

use function array_map;
use function count;
use function is_array;
use function iterator_to_array;

/**
 * Describes an Error found during the parse, validate, or
 * execute phases of performing a GraphQL operation. In addition to a message
 * and stack trace, it also includes information about the locations in a
 * GraphQL document and/or execution result that correspond to the Error.
 *
 * When the error was caused by an exception thrown in resolver, original exception
 * is available via `getPrevious()`.
 *
 * Also read related docs on [error handling](error-handling.md)
 *
 * Class extends standard PHP `\Exception`, so all standard methods of base `\Exception` class
 * are available in addition to those listed below.
 */
class Error extends Exception implements JsonSerializable, ClientAware, ProvidesExtensions
{
    /**
     * Lazily initialized.
     *
     * @var array<int, SourceLocation>
     */
    private array $locations;

    /**
     * An array describing the JSON-path into the execution response which
     * corresponds to this error. Only included for errors during execution.
     *
     * @var array<int, int|string>|null
     */
    public ?array $path;

    /**
     * An array of GraphQL AST Nodes corresponding to this error.
     *
     * @var array<Node>|null
     */
    public ?array $nodes;

    /**
     * The source GraphQL document for the first location of this error.
     *
     * Note that if this Error represents more than one node, the source may not
     * represent nodes after the first node.
     */
    private ?Source $source;

    /** @var array<int, int>|null */
    private ?array $positions;

    private bool $isClientSafe;

    /** @var array<string, mixed>|null */
    protected ?array $extensions;

    /**
     * @param iterable<Node>|Node|null    $nodes
     * @param array<int, int>|null        $positions
     * @param array<int, int|string>|null $path
     * @param array<string, mixed>|null   $extensions
     */
    public function __construct(
        string $message = '',
        $nodes = null,
        ?Source $source = null,
        ?array $positions = null,
        ?array $path = null,
        ?Throwable $previous = null,
        ?array $extensions = null
    ) {
        parent::__construct($message, 0, $previous);

        // Compute list of blame nodes.
        if ($nodes instanceof Traversable) {
            $this->nodes = iterator_to_array($nodes);
        } elseif (is_array($nodes)) {
            $this->nodes = $nodes;
        } elseif ($nodes !== null) {
            $this->nodes = [$nodes];
        } else {
            $this->nodes = null;
        }

        $this->source    = $source;
        $this->positions = $positions;
        $this->path      = $path;

        if (is_array($extensions) && count($extensions) > 0) {
            $this->extensions = $extensions;
        } elseif ($previous instanceof ProvidesExtensions) {
            $this->extensions = $previous->getExtensions();
        } else {
            $this->extensions = null;
        }

        if ($previous instanceof ClientAware) {
            $this->isClientSafe = $previous->isClientSafe();
        } elseif ($previous !== null) {
            $this->isClientSafe = false;
        } else {
            $this->isClientSafe = true;
        }
    }

    /**
     * Given an arbitrary Error, presumably thrown while attempting to execute a
     * GraphQL operation, produce a new GraphQLError aware of the location in the
     * document responsible for the original Error.
     *
     * @param mixed                       $error
     * @param iterable<Node>|Node|null    $nodes
     * @param array<int, int|string>|null $path
     */
    public static function createLocatedError($error, $nodes = null, ?array $path = null): Error
    {
        if ($error instanceof self) {
            if ($error->isLocated()) {
                return $error;
            }

            $nodes ??= $error->nodes;
            $path  ??= $error->path;
        }

        $source        = null;
        $originalError = null;
        $positions     = [];
        $extensions    = [];

        if ($error instanceof self) {
            $message       = $error->getMessage();
            $originalError = $error;
            $nodes         = $error->nodes ?? $nodes; // TODO why does this line change the behaviour?
            $source        = $error->source;
            $positions     = $error->positions;
            $extensions    = $error->extensions;
        } elseif ($error instanceof InvariantViolation) {
            $message       = $error->getMessage();
            $originalError = $error->getPrevious() ?? $error;
        } elseif ($error instanceof Throwable) {
            $message       = $error->getMessage();
            $originalError = $error;
        } else {
            $message = (string) $error;
        }

        $nonEmptyMessage = $message === '' || $message === null
            ? 'An unknown error occurred.'
            : $message;

        return new static(
            $nonEmptyMessage,
            $nodes,
            $source,
            $positions,
            $path,
            $originalError,
            $extensions
        );
    }

    protected function isLocated(): bool
    {
        return $this->path !== null
            && count($this->path) > 0
            && $this->nodes !== null
            && count($this->nodes) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatError(Error $error): array
    {
        return $error->toSerializableArray();
    }

    public function isClientSafe(): bool
    {
        return $this->isClientSafe;
    }

    public function getSource(): ?Source
    {
        return $this->source
            ??= $this->nodes[0]->loc->source
            ?? null;
    }

    /**
     * @return array<int, int>
     */
    public function getPositions(): array
    {
        if (! isset($this->positions)) {
            $this->positions = [];

            if (isset($this->nodes)) {
                foreach ($this->nodes as $node) {
                    $start = $node->loc->start ?? null;
                    if ($start === null) {
                        continue;
                    }

                    $this->positions[] = $start;
                }
            }
        }

        return $this->positions;
    }

    /**
     * An array of locations within the source GraphQL document which correspond to this error.
     *
     * Each entry has information about `line` and `column` within source GraphQL document:
     * $location->line;
     * $location->column;
     *
     * Errors during validation often contain multiple locations, for example to
     * point out to field mentioned in multiple fragments. Errors during execution include a
     * single location, the field which produced the error.
     *
     * @return array<int, SourceLocation>
     *
     * @api
     */
    public function getLocations(): array
    {
        if (! isset($this->locations)) {
            $positions = $this->getPositions();
            $source    = $this->getSource();
            $nodes     = $this->getNodes();

            $this->locations = [];
            if ($source !== null && count($positions) !== 0) {
                foreach ($positions as $position) {
                    $this->locations[] = $source->getLocation($position);
                }
            } elseif ($nodes !== null && count($nodes) !== 0) {
                foreach ($nodes as $node) {
                    if (! isset($node->loc->source)) {
                        continue;
                    }

                    $this->locations[] = $node->loc->source->getLocation($node->loc->start);
                }
            }
        }

        return $this->locations;
    }

    /**
     * @return array<Node>|null
     */
    public function getNodes(): ?array
    {
        return $this->nodes;
    }

    /**
     * Returns an array describing the path from the root value to the field which produced this error.
     * Only included for execution errors.
     *
     * @return array<int, int|string>|null
     *
     * @api
     */
    public function getPath(): ?array
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExtensions(): ?array
    {
        return $this->extensions;
    }

    /**
     * Returns array representation of error suitable for serialization
     *
     * @deprecated Use FormattedError::createFromException() instead
     *
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public function toSerializableArray(): array
    {
        $arr = [
            'message' => $this->getMessage(),
        ];

        $locations = array_map(
            static fn (SourceLocation $loc): array => $loc->toSerializableArray(),
            $this->getLocations()
        );

        if (count($locations) > 0) {
            $arr['locations'] = $locations;
        }

        if ($this->path !== null && count($this->path) > 0) {
            $arr['path'] = $this->path;
        }

        if (isset($this->extensions) && count($this->extensions) > 0) {
            $arr['extensions'] = $this->extensions;
        }

        return $arr;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return array<string, mixed> data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): array
    {
        return FormattedError::createFromException($this);
    }

    public function __toString(): string
    {
        return FormattedError::printError($this);
    }
}
