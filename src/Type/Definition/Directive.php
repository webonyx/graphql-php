<?php
namespace GraphQL\Type\Definition;

class Directive
{
    public static $internalDirectives;

    /**
     * @return Directive
     */
    public static function ifDirective()
    {
        $internal = self::getInternalDirectives();
        return $internal['if'];
    }

    /**
     * @return Directive
     */
    public static function unlessDirective()
    {
        $internal = self::getInternalDirectives();
        return $internal['unless'];
    }

    public static function getInternalDirectives()
    {
        if (!self::$internalDirectives) {
            self::$internalDirectives = [
                'if' => new self([
                    'name' => 'if',
                    'description' => 'Directs the executor to omit this field if the argument provided is false.',
                    'type' => Type::nonNull(Type::boolean()),
                    'onOperation' => false,
                    'onFragment' => false,
                    'onField' => true
                ]),
                'unless' => new self([
                    'name' => 'unless',
                    'description' => 'Directs the executor to omit this field if the argument provided is true.',
                    'type' => Type::nonNull(Type::boolean()),
                    'onOperation' => false,
                    'onFragment' => false,
                    'onField' => true
                ])
            ];
        }
        return self::$internalDirectives;
    }

    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var Type
     */
    public $type;

    /**
     * @var boolean
     */
    public $onOperation;

    /**
     * @var boolean
     */
    public $onFragment;

    /**
     * @var boolean
     */
    public $onField;

    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
