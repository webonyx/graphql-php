<?php
namespace GraphQL\Executor;

use GraphQL\Error;

class ExecutionResult
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var Error[]
     */
    public $errors;

    /**
     * @param array $data
     * @param array $errors
     */
    public function __construct(array $data = null, array $errors = [])
    {
        $this->data = $data;
        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = ['data' => $this->data];

        if (!empty($this->errors)) {
            $result['errors'] = array_map(function($e)
            {
                $errorClass = $e instanceof Error ? get_class($e):'\GraphQL\Error';
                return $errorClass::formatError($e);
            }, $this->errors);
        }

        return $result;
    }
}
