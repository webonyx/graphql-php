<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Utils\Utils;
use function array_key_exists;
use function is_array;

/**
 * Class Directive
 */
class Directive
{
    public const DEFAULT_DEPRECATION_REASON = 'No longer supported';

    /** @var Directive[] */
    public static $internalDirectives;

    // Schema Definitions

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var string[] */
    public $locations;

    /** @var FieldArgument[] */
    public $args;

    /** @var DirectiveDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config)
    {
        if (isset($config['args'])) {
            $args = [];
            foreach ($config['args'] as $name => $arg) {
                if (is_array($arg)) {
                    $args[] = new FieldArgument($arg + ['name' => $name]);
                } else {
                    $args[] = $arg;
                }
            }
            $this->args = $args;
            unset($config['args']);
        }
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }

        Utils::invariant($this->name, 'Directive must be named.');
        Utils::invariant(is_array($this->locations), 'Must provide locations for directive.');
        $this->config = $config;
    }

    /**
     * @return Directive
     */
    public static function includeDirective()
    {
        $internal = self::getInternalDirectives();

        return $internal['include'];
    }

    /**
     * @return Directive[]
     */
    public static function getInternalDirectives()
    {
        if (! self::$internalDirectives) {
            self::$internalDirectives = [
                'include'    => new self([
                    'name'        => 'include',
                    'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
                    'locations'   => [
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT,
                    ],
                    'args'        => [new FieldArgument([
                        'name'        => 'if',
                        'type'        => Type::nonNull(Type::boolean()),
                        'description' => 'Included when true.',
                    ]),
                    ],
                ]),
                'skip'       => new self([
                    'name'        => 'skip',
                    'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
                    'locations'   => [
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT,
                    ],
                    'args'        => [new FieldArgument([
                        'name'        => 'if',
                        'type'        => Type::nonNull(Type::boolean()),
                        'description' => 'Skipped when true.',
                    ]),
                    ],
                ]),
                'deprecated' => new self([
                    'name'        => 'deprecated',
                    'description' => 'Marks an element of a GraphQL schema as no longer supported.',
                    'locations'   => [
                        DirectiveLocation::FIELD_DEFINITION,
                        DirectiveLocation::ENUM_VALUE,
                    ],
                    'args'        => [new FieldArgument([
                        'name'         => 'reason',
                        'type'         => Type::string(),
                        'description'  =>
                            'Explains why this element was deprecated, usually also including a ' .
                            'suggestion for how to access supported similar data. Formatted ' .
                            'in [Markdown](https://daringfireball.net/projects/markdown/).',
                        'defaultValue' => self::DEFAULT_DEPRECATION_REASON,
                    ]),
                    ],
                ]),
            ];
        }

        return self::$internalDirectives;
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
     * @return bool
     */
    public static function isSpecifiedDirective(Directive $directive)
    {
        return array_key_exists($directive->name, self::getInternalDirectives());
    }
}
