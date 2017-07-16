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
    private $allowsMutations;

    /**
     * Creates an instance from given array
     *
     * @param array $params
     * @param bool $allowsMutations
     *
     * @return static
     */
    public static function create(array $params, $allowsMutations = true)
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
        $instance->allowsMutations = (bool) $allowsMutations;

        return $instance;
    }

    /**
     * @return array
     */
    public function validate()
    {
        $errors = [];
        if (!$this->query && !$this->queryId) {
            $errors[] = 'GraphQL Request must include at least one of those two parameters: "query" or "queryId"';
        }
        if ($this->query && $this->queryId) {
            $errors[] = 'GraphQL Request parameters "query" and "queryId" are mutually exclusive';
        }

        if ($this->query !== null && (!is_string($this->query) || empty($this->query))) {
            $errors[] = 'GraphQL Request parameter "query" must be string, but got ' .
                Utils::printSafeJson($this->query);
        }
        if ($this->queryId !== null && (!is_string($this->queryId) || empty($this->queryId))) {
            $errors[] = 'GraphQL Request parameter "queryId" must be string, but got ' .
                Utils::printSafeJson($this->queryId);
        }

        if ($this->operation !== null && (!is_string($this->operation) || empty($this->operation))) {
            $errors[] = 'GraphQL Request parameter "operation" must be string, but got ' .
                Utils::printSafeJson($this->operation);
        }
        if ($this->variables !== null && (!is_array($this->variables) || isset($this->variables[0]))) {
            $errors[] = 'GraphQL Request parameter "variables" must be object, but got ' .
                Utils::printSafeJson($this->variables);
        }
        return $errors;
    }

    public function getOriginalInput()
    {
        return $this->originalInput;
    }

    /**
     * @return bool
     */
    public function allowsMutation()
    {
        return $this->allowsMutations;
    }
}
