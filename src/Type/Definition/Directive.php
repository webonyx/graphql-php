<?php
namespace GraphQL\Type\Definition;

class Directive
{
    public static $internalDirectives;

    /**
     * @return Directive
     */
    public static function includeDirective()
    {
        $internal = self::getInternalDirectives();
        return $internal['include'];
    }

    /**
     * @return Directive
     */
    public static function skipDirective()
    {
        $internal = self::getInternalDirectives();
        return $internal['skip'];
    }

    public static function getInternalDirectives()
    {
        if (!self::$internalDirectives) {
            self::$internalDirectives = [
                'include' => new self([
                    'name' => 'include',
                    'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
                    'args' => [
                        new FieldArgument([
                            'name' => 'if',
                            'type' => Type::nonNull(Type::boolean()),
                            'description' => 'Included when true.'
                        ])
                    ],
                    'onOperation' => false,
                    'onFragment' => true,
                    'onField' => true
                ]),
                'skip' => new self([
                    'name' => 'skip',
                    'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
                    'args' => [
                        new FieldArgument([
                            'name' => 'if',
                            'type' => Type::nonNull(Type::boolean()),
                            'description' => 'Skipped when true'
                        ])
                    ],
                    'onOperation' => false,
                    'onFragment' => true,
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
     * @var FieldArgument[]
     */
    public $args;

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
