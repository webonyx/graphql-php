<?php
namespace GraphQL\Type\Definition;

/**
 * Class Directive
 * @package GraphQL\Type\Definition
 */
class Directive
{
    const DEFAULT_DEPRECATION_REASON = 'No longer supported';

    /**
     * @var array
     */
    public static $internalDirectives;

    /**
     * @var array
     */
    public static $directiveLocations = [
        // Operations:
        'QUERY' => 'QUERY',
        'MUTATION' => 'MUTATION',
        'SUBSCRIPTION' => 'SUBSCRIPTION',
        'FIELD' => 'FIELD',
        'FRAGMENT_DEFINITION' => 'FRAGMENT_DEFINITION',
        'FRAGMENT_SPREAD' => 'FRAGMENT_SPREAD',
        'INLINE_FRAGMENT' => 'INLINE_FRAGMENT',

        // Schema Definitions
        'SCHEMA' => 'SCHEMA',
        'SCALAR' => 'SCALAR',
        'OBJECT' => 'OBJECT',
        'FIELD_DEFINITION' => 'FIELD_DEFINITION',
        'ARGUMENT_DEFINITION' => 'ARGUMENT_DEFINITION',
        'INTERFACE' => 'INTERFACE',
        'UNION' => 'UNION',
        'ENUM' => 'ENUM',
        'ENUM_VALUE' => 'ENUM_VALUE',
        'INPUT_OBJECT' => 'INPUT_OBJECT',
        'INPUT_FIELD_DEFINITION' => 'INPUT_FIELD_DEFINITION'
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
     * @return Directive
     */
    public static function deprecatedDirective()
    {
        $internal = self::getInternalDirectives();
        return $internal['deprecated'];
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
                ]),
                'deprecated' => new self([
                    'name' => 'deprecated',
                    'description' => 'Marks an element of a GraphQL schema as no longer supported.',
                    'locations' => [
                        self::$directiveLocations['FIELD_DEFINITION'],
                        self::$directiveLocations['ENUM_VALUE']
                    ],
                    'args' => [
                        new FieldArgument([
                            'name' => 'reason',
                            'type' => Type::string(),
                            'description' =>
                                'Explains why this element was deprecated, usually also including a ' .
                                'suggestion for how to access supported similar data. Formatted ' .
                                'in [Markdown](https://daringfireball.net/projects/markdown/).',
                            'defaultValue' => self::DEFAULT_DEPRECATION_REASON
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
