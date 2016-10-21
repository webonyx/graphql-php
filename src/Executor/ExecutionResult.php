<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;

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
     * @var array[]
     */
    public $extensions;

    /**
     * @param array $data
     * @param array $errors
     * @param array $extensions
     */
    public function __construct(array $data = null, array $errors = [], array $extensions = [])
    {
        $this->data = $data;
        $this->errors = $errors;
        $this->extensions = $extensions;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = [];

        if (null !== $this->data) {
            $result['data'] = $this->data;
        }

        if (!empty($this->errors)) {
            $result['errors'] = array_map(['GraphQL\Error\Error', 'formatError'], $this->errors);
        }
        
        if (!empty($this->extensions)) {
            $result['extensions'] = (array) $this->extensions;
        }

        return $result;
    }
}
