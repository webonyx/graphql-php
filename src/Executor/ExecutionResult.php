<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;

class ExecutionResult implements \JsonSerializable
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
     * @var callable
     */
    private $errorFormatter = ['GraphQL\Error\Error', 'formatError'];

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
     * @param callable $errorFormatter
     * @return $this
     */
    public function setErrorFormatter(callable $errorFormatter)
    {
        $this->errorFormatter = $errorFormatter;
        return $this;
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
            $result['errors'] = array_map($this->errorFormatter, $this->errors);
        }
        
        if (!empty($this->extensions)) {
            $result['extensions'] = (array) $this->extensions;
        }

        return $result;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
