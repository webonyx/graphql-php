<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\DirectiveDefinitionNode;

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

    // Schema Definitions


    /**
     * @var array
     * @deprecated as of 8.0 (use DirectiveLocation constants directly)
     */
    public static $directiveLocations = [
        // Operations:
        DirectiveLocation::QUERY => DirectiveLocation::QUERY,
        DirectiveLocation::MUTATION => DirectiveLocation::MUTATION,
        DirectiveLocation::SUBSCRIPTION => DirectiveLocation::SUBSCRIPTION,
        DirectiveLocation::FIELD => DirectiveLocation::FIELD,
        DirectiveLocation::FRAGMENT_DEFINITION => DirectiveLocation::FRAGMENT_DEFINITION,
        DirectiveLocation::FRAGMENT_SPREAD => DirectiveLocation::FRAGMENT_SPREAD,
        DirectiveLocation::INLINE_FRAGMENT => DirectiveLocation::INLINE_FRAGMENT,

        // Schema Definitions
        DirectiveLocation::SCHEMA => DirectiveLocation::SCHEMA,
        DirectiveLocation::SCALAR => DirectiveLocation::SCALAR,
        DirectiveLocation::OBJECT => DirectiveLocation::OBJECT,
        DirectiveLocation::FIELD_DEFINITION => DirectiveLocation::FIELD_DEFINITION,
        DirectiveLocation::ARGUMENT_DEFINITION => DirectiveLocation::ARGUMENT_DEFINITION,
        DirectiveLocation::IFACE => DirectiveLocation::IFACE,
        DirectiveLocation::UNION => DirectiveLocation::UNION,
        DirectiveLocation::ENUM => DirectiveLocation::ENUM,
        DirectiveLocation::ENUM_VALUE => DirectiveLocation::ENUM_VALUE,
        DirectiveLocation::INPUT_OBJECT => DirectiveLocation::INPUT_OBJECT,
        DirectiveLocation::INPUT_FIELD_DEFINITION => DirectiveLocation::INPUT_FIELD_DEFINITION
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
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT,
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
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT
                    ],
                    'args' => [
                        new FieldArgument([
                            'name' => 'if',
                            'type' => Type::nonNull(Type::boolean()),
                            'description' => 'Skipped when true.'
                        ])
                    ]
                ]),
                'deprecated' => new self([
                    'name' => 'deprecated',
                    'description' => 'Marks an element of a GraphQL schema as no longer supported.',
                    'locations' => [
                        DirectiveLocation::FIELD_DEFINITION,
                        DirectiveLocation::ENUM_VALUE
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
     * @var DirectiveDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * Directive constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }
        $this->config = $config;
    }
}
