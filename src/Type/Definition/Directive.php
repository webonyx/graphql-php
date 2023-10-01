<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Type\Registry\DefaultStandardTypeRegistry;

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

    public const INTERNAL_DIRECTIVE_NAMES = [
        self::INCLUDE_NAME,
        self::SKIP_NAME,
        self::DEPRECATED_NAME,
    ];

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
        return DefaultStandardTypeRegistry::instance()->internalDirectives();
    }

    public static function isSpecifiedDirective(Directive $directive): bool
    {
        return \in_array($directive->name, self::INTERNAL_DIRECTIVE_NAMES, true);
    }
}
