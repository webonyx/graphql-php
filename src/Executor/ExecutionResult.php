<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

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
    private $errorFormatter;

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
     * @param bool|int $debug
     * @return array
     */
    public function toArray($debug = false)
    {
        $result = [];

        if (!empty($this->errors)) {
            if ($debug) {
                $errorFormatter = function($e) use ($debug) {
                    return FormattedError::createFromException($e, $debug);
                };
            } else if (!$this->errorFormatter) {
                $errorFormatter = function($e) {
                    return FormattedError::createFromException($e, false);
                };
            } else {
                $errorFormatter = $this->errorFormatter;
            }
            $result['errors'] = array_map($errorFormatter, $this->errors);
        }

        if (null !== $this->data) {
            $result['data'] = $this->data;
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
