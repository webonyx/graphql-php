<?php
namespace GraphQL\Server;

/**
 * Structure representing parsed HTTP parameters for GraphQL operation
 */
class OperationParams
{
    /**
     * Id of the query (when using persistent queries).
     *
     * Valid aliases (case-insensitive):
     * - id
     * - queryId
     * - documentId
     *
     * @api
     * @var string
     */
    public $queryId;

    /**
     * @api
     * @var string
     */
    public $query;

    /**
     * @api
     * @var string
     */
    public $operation;

    /**
     * @api
     * @var array
     */
    public $variables;

    /**
     * @var array
     */
    private $originalInput;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * Creates an instance from given array
     *
     * @api
     * @param array $params
     * @param bool $readonly
     * @return OperationParams
     */
    public static function create(array $params, $readonly = false)
    {
        $instance = new static();

        $params = array_change_key_case($params, CASE_LOWER);
        $instance->originalInput = $params;

        $params += [
            'query' => null,
            'queryid' => null,
            'documentid' => null, // alias to queryid
            'id' => null, // alias to queryid
            'operationname' => null,
            'variables' => null
        ];

        if ($params['variables'] === "") {
            $params['variables'] = null;
        }

        if (is_string($params['variables'])) {
            $tmp = json_decode($params['variables'], true);
            if (!json_last_error()) {
                $params['variables'] = $tmp;
            }
        }

        $instance->query = $params['query'];
        $instance->queryId = $params['queryid'] ?: $params['documentid'] ?: $params['id'];
        $instance->operation = $params['operationname'];
        $instance->variables = $params['variables'];
        $instance->readOnly = (bool) $readonly;

        return $instance;
    }

    /**
     * @api
     * @param string $key
     * @return mixed
     */
    public function getOriginalInput($key)
    {
        return isset($this->originalInput[$key]) ? $this->originalInput[$key] : null;
    }

    /**
     * Indicates that operation is executed in read-only context
     * (e.g. via HTTP GET request)
     *
     * @api
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }
}
