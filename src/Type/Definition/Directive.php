<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\DirectiveLocation;

/**
 * @phpstan-import-type ArgumentListConfig from Argument
 *
 * @phpstan-type DirectiveConfig array{
 *   name: string,
 *   description?: string|null,
 *   args?: ArgumentListConfig|null,
 *   locations: array<string>,
 *   isRepeatable?: bool|null,
 *   astNode?: DirectiveDefinitionNode|null
 * }
 */
class Directive
{
    public const DEFAULT_DEPRECATION_REASON = 'No longer supported';

    public const INCLUDE_NAME = 'include';
    public const IF_ARGUMENT_NAME = 'if';
    public const SKIP_NAME = 'skip';
    public const DEPRECATED_NAME = 'deprecated';
    public const REASON_ARGUMENT_NAME = 'reason';

    /**
     * Lazily initialized.
     *
     * @var array<string, Directive>|null
     */
    protected static ?array $internalDirectives = null;

    public string $name;

    public ?string $description;

    /** @var array<int, Argument> */
    public array $args;

    public bool $isRepeatable;

    /** @var array<string> */
    public array $locations;

    public ?DirectiveDefinitionNode $astNode;

    /**
     * @var array<string, mixed>
     *
     * @phpstan-var DirectiveConfig
     */
    public array $config;

    /**
     * @param array<string, mixed> $config
     *
     * @phpstan-param DirectiveConfig $config
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->description = $config['description'] ?? null;
        $this->args = isset($config['args'])
            ? Argument::listFromConfig($config['args'])
            : [];
        $this->isRepeatable = $config['isRepeatable'] ?? false;
        $this->locations = $config['locations'];
        $this->astNode = $config['astNode'] ?? null;

        $this->config = $config;
    }

    /**
     * @throws InvariantViolation
     *
     * @return array<string, Directive>
     */
    public static function getInternalDirectives(): array
    {
        return [
            self::INCLUDE_NAME => self::includeDirective(),
            self::SKIP_NAME => self::skipDirective(),
            self::DEPRECATED_NAME => self::deprecatedDirective(),
        ];
    }

    /** @throws InvariantViolation */
    public static function includeDirective(): Directive
    {
        return self::$internalDirectives[self::INCLUDE_NAME] ??= new self([
            'name' => self::INCLUDE_NAME,
            'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
            'locations' => [
                DirectiveLocation::FIELD,
                DirectiveLocation::FRAGMENT_SPREAD,
                DirectiveLocation::INLINE_FRAGMENT,
            ],
            'args' => [
                self::IF_ARGUMENT_NAME => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Included when true.',
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function skipDirective(): Directive
    {
        return self::$internalDirectives[self::SKIP_NAME] ??= new self([
            'name' => self::SKIP_NAME,
            'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
            'locations' => [
                DirectiveLocation::FIELD,
                DirectiveLocation::FRAGMENT_SPREAD,
                DirectiveLocation::INLINE_FRAGMENT,
            ],
            'args' => [
                self::IF_ARGUMENT_NAME => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Skipped when true.',
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function deprecatedDirective(): Directive
    {
        return self::$internalDirectives[self::DEPRECATED_NAME] ??= new self([
            'name' => self::DEPRECATED_NAME,
            'description' => 'Marks an element of a GraphQL schema as no longer supported.',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::ENUM_VALUE,
                DirectiveLocation::ARGUMENT_DEFINITION,
                DirectiveLocation::INPUT_FIELD_DEFINITION,
            ],
            'args' => [
                self::REASON_ARGUMENT_NAME => [
                    'type' => Type::string(),
                    'description' => 'Explains why this element was deprecated, usually also including a suggestion for how to access supported similar data. Formatted using the Markdown syntax, as specified by [CommonMark](https://commonmark.org/).',
                    'defaultValue' => self::DEFAULT_DEPRECATION_REASON,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function isSpecifiedDirective(Directive $directive): bool
    {
        return \array_key_exists($directive->name, self::getInternalDirectives());
    }

    public static function resetCachedInstances(): void
    {
        self::$internalDirectives = null;
    }
}
