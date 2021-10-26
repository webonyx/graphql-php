<?php

declare(strict_types=1);

namespace GraphQL\Server;

use function array_change_key_case;
use function array_walk;
use function is_string;
use function json_decode;
use function json_last_error;

use const CASE_LOWER;
use const JSON_ERROR_NONE;

/**
 * Structure representing parsed HTTP parameters for GraphQL operation.
 */
class OperationParams
{
    /**
     * Id of the query (when using persisted queries).
     *
     * Valid aliases (case-insensitive):
     * - id
     * - queryId
     * - documentId
     *
     * @api
     * @var mixed
     */
    public $queryId;

    /**
     * @api
     * @var mixed
     */
    public $query;

    /**
     * @api
     * @var mixed
     */
    public $operation;

    /**
     * @api
     * @var mixed
     */
    public $variables;

    /**
     * @api
     * @var mixed
     */
    public $extensions;

    /** @api */
    public bool $readOnly;

    /** @var array<string, mixed> */
    protected array $originalInput;

    /**
     * Creates an instance from given array
     *
     * @param array<string, mixed> $params
     *
     * @api
     */
    public static function create(array $params, bool $readonly = false): OperationParams
    {
        $instance = new static();

        $params                  = array_change_key_case($params, CASE_LOWER);
        $instance->originalInput = $params;

        $params += [
            'query' => null,
            'queryid' => null,
            'documentid' => null, // alias to queryid
            'id' => null, // alias to queryid
            'operationname' => null,
            'variables' => null,
            'extensions' => null,
        ];

        array_walk($params, static function (&$value): void {
            if ($value !== '') {
                return;
            }

            $value = null;
        });

        $instance->query      = $params['query'];
        $instance->queryId    = $params['queryid'] ?? $params['documentid'] ?? $params['id'];
        $instance->operation  = $params['operationname'];
        $instance->variables  = static::decodeIfJSON($params['variables']);
        $instance->extensions = static::decodeIfJSON($params['extensions']);
        $instance->readOnly   = $readonly;

        // Apollo server/client compatibility
        if (
            isset($instance->extensions['persistedQuery']['sha256Hash'])
            && $instance->query === null
            && $instance->queryId === null
        ) {
            $instance->queryId = $instance->extensions['persistedQuery']['sha256Hash'];
        }

        return $instance;
    }

    /**
     * @return mixed
     *
     * @api
     */
    public function getOriginalInput(string $key)
    {
        return $this->originalInput[$key] ?? null;
    }

    /**
     * Indicates that operation is executed in read-only context
     * (e.g. via HTTP GET request)
     *
     * @api
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Decodes the value if it is JSON, otherwise returns it unchanged.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected static function decodeIfJSON($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
