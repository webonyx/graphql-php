<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\EnumValueDefinitionNode;

/**
 * @phpstan-type EnumValueConfig array{
 *   name: string,
 *   value?: mixed,
 *   deprecationReason?: string|null,
 *   description?: string|null,
 *   astNode?: EnumValueDefinitionNode|null,
 * }
 */
class EnumValueDefinition
{
    public string $name;

    /** @var mixed */
    public $value;

    public ?string $deprecationReason;

    public ?string $description;

    public ?EnumValueDefinitionNode $astNode;

    /** @var array<string, mixed> */
    public $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->value = $config['value'] ?? null;
        $this->deprecationReason = $config['deprecationReason'] ?? null;
        $this->description = $config['description'] ?? null;
        $this->astNode = $config['astNode'] ?? null;

        $this->config = $config;
    }

    public function isDeprecated(): bool
    {
        return (bool) $this->deprecationReason;
    }
}
