<?php
namespace GraphQL\Server;

/**
 * Class QueryParams
 * Represents all available parsed query parameters
 *
 * @package GraphQL\Server
 */
class OperationParams
{
    /**
     * @var string
     */
    public $query;

    /**
     * @var string
     */
    public $queryId;

    /**
     * @var string
     */
    public $operation;

    /**
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
     * @param array $params
     * @param bool $readonly
     *
     * @return static
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
            'operation' => null,
            'variables' => null
        ];

        if (is_string($params['variables'])) {
            $tmp = json_decode($params['variables'], true);
            if (!json_last_error()) {
                $params['variables'] = $tmp;
            }
        }

        $instance->query = $params['query'];
        $instance->queryId = $params['queryid'] ?: $params['documentid'] ?: $params['id'];
        $instance->operation = $params['operation'];
        $instance->variables = $params['variables'];
        $instance->readOnly = (bool) $readonly;

        return $instance;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getOriginalInput($key)
    {
        return isset($this->originalInput[$key]) ? $this->originalInput[$key] : null;
    }

    /**
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }
}
