<?php
namespace GraphQL\Server;

use GraphQL\Utils\Utils;

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
        $instance->originalInput = $params;

        $params = array_change_key_case($params, CASE_LOWER);

        $params += [
            'query' => null,
            'queryid' => null,
            'documentid' => null, // alias to queryid
            'operation' => null,
            'variables' => null
        ];

        $instance->query = $params['query'];
        $instance->queryId = $params['queryid'] ?: $params['documentid'];
        $instance->operation = $params['operation'];
        $instance->variables = $params['variables'];
        $instance->readOnly = (bool) $readonly;

        return $instance;
    }

    /**
     * @return array
     */
    public function getOriginalInput()
    {
        return $this->originalInput;
    }

    /**
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }
}
