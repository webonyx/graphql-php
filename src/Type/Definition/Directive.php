<?php
namespace GraphQL\Type\Definition;

/**
 * Class Directive
 * @package GraphQL\Type\Definition
 */
class Directive
{
    /**
     * @var array
     */
    public static $internalDirectives;

    /**
     * @var array
     */
    public static $directiveLocations = [
        'QUERY' => 'QUERY',
        'MUTATION' => 'MUTATION',
        'SUBSCRIPTION' => 'SUBSCRIPTION',
        'FIELD' => 'FIELD',
        'FRAGMENT_DEFINITION' => 'FRAGMENT_DEFINITION',
        'FRAGMENT_SPREAD' => 'FRAGMENT_SPREAD',
        'INLINE_FRAGMENT' => 'INLINE_FRAGMENT',
    ];

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

    /**
     * @return array
     */
    public static function getInternalDirectives()
    {
        if (!self::$internalDirectives) {
            self::$internalDirectives = [
                'include' => new self([
                    'name' => 'include',
                    'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
                    'locations' => [
                        self::$directiveLocations['FIELD'],
                        self::$directiveLocations['FRAGMENT_SPREAD'],
                        self::$directiveLocations['INLINE_FRAGMENT'],
                    ],
                    'args' => [
                        new FieldArgument([
                            'name' => 'if',
                            'type' => Type::nonNull(Type::boolean()),
                            'description' => 'Included when true.'
                        ])
                    ],
                ]),
                'skip' => new self([
                    'name' => 'skip',
                    'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
                    'locations' => [
                        self::$directiveLocations['FIELD'],
                        self::$directiveLocations['FRAGMENT_SPREAD'],
                        self::$directiveLocations['INLINE_FRAGMENT']
                    ],
                    'args' => [
                        new FieldArgument([
                            'name' => 'if',
                            'type' => Type::nonNull(Type::boolean()),
                            'description' => 'Skipped when true'
                        ])
                    ]
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
     * Values from self::$locationMap
     *
     * @var array
     */
    public $locations;

    /**
     * @var FieldArgument[]
     */
    public $args;

    /**
     * Directive constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
