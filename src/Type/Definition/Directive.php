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

    const LOCATION_QUERY = 'QUERY';
    const LOCATION_MUTATION = 'MUTATION';
    const LOCATION_SUBSCRIPTION = 'SUBSCRIPTION';
    const LOCATION_FIELD = 'FIELD';
    const LOCATION_FRAGMENT_DEFINITION = 'FRAGMENT_DEFINITION';
    const LOCATION_FRAGMENT_SPREAD = 'FRAGMENT_SPREAD';
    const LOCATION_INLINE_FRAGMENT = 'INLINE_FRAGMENT';

    // Schema Definitions
    const LOCATION_SCHEMA = 'SCHEMA';
    const LOCATION_SCALAR = 'SCALAR';
    const LOCATION_OBJECT = 'OBJECT';
    const LOCATION_FIELD_DEFINITION = 'FIELD_DEFINITION';
    const LOCATION_ARGUMENT_DEFINITION = 'ARGUMENT_DEFINITION';
    const LOCATION_INTERFACE = 'INTERFACE';
    const LOCATION_UNION = 'UNION';
    const LOCATION_ENUM = 'ENUM';
    const LOCATION_ENUM_VALUE = 'ENUM_VALUE';
    const LOCATION_INPUT_OBJECT = 'INPUT_OBJECT';
    const LOCATION_INPUT_FIELD_DEFINITION = 'INPUT_FIELD_DEFINITION';


    /**
     * @var array
     * @deprecated Just use constants directly
     */
    public static $directiveLocations = [
        // Operations:
        self::LOCATION_QUERY => self::LOCATION_QUERY,
        self::LOCATION_MUTATION => self::LOCATION_MUTATION,
        self::LOCATION_SUBSCRIPTION => self::LOCATION_SUBSCRIPTION,
        self::LOCATION_FIELD => self::LOCATION_FIELD,
        self::LOCATION_FRAGMENT_DEFINITION => self::LOCATION_FRAGMENT_DEFINITION,
        self::LOCATION_FRAGMENT_SPREAD => self::LOCATION_FRAGMENT_SPREAD,
        self::LOCATION_INLINE_FRAGMENT => self::LOCATION_INLINE_FRAGMENT,

        // Schema Definitions
        self::LOCATION_SCHEMA => self::LOCATION_SCHEMA,
        self::LOCATION_SCALAR => self::LOCATION_SCALAR,
        self::LOCATION_OBJECT => self::LOCATION_OBJECT,
        self::LOCATION_FIELD_DEFINITION => self::LOCATION_FIELD_DEFINITION,
        self::LOCATION_ARGUMENT_DEFINITION => self::LOCATION_ARGUMENT_DEFINITION,
        self::LOCATION_INTERFACE => self::LOCATION_INTERFACE,
        self::LOCATION_UNION => self::LOCATION_UNION,
        self::LOCATION_ENUM => self::LOCATION_ENUM,
        self::LOCATION_ENUM_VALUE => self::LOCATION_ENUM_VALUE,
        self::LOCATION_INPUT_OBJECT => self::LOCATION_INPUT_OBJECT,
        self::LOCATION_INPUT_FIELD_DEFINITION => self::LOCATION_INPUT_FIELD_DEFINITION
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
                        self::LOCATION_FIELD,
                        self::LOCATION_FRAGMENT_SPREAD,
                        self::LOCATION_INLINE_FRAGMENT,
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
                        self::LOCATION_FIELD,
                        self::LOCATION_FRAGMENT_SPREAD,
                        self::LOCATION_INLINE_FRAGMENT
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
                        self::LOCATION_FIELD_DEFINITION,
                        self::LOCATION_ENUM_VALUE
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
