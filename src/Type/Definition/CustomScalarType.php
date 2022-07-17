<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Utils\AST;

use function is_callable;

/**
 * @phpstan-type InputCustomScalarConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   serialize?: callable(mixed): mixed,
 *   parseValue: callable(mixed): mixed,
 *   parseLiteral: callable(ValueNode&Node, array<string, mixed>|null): mixed,
 *   astNode?: ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<ScalarTypeExtensionNode>|null
 * }
 * @phpstan-type OutputCustomScalarConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   serialize: callable(mixed): mixed,
 *   parseValue?: callable(mixed): mixed,
 *   parseLiteral?: callable(ValueNode&Node, array<string, mixed>|null): mixed,
 *   astNode?: ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<ScalarTypeExtensionNode>|null
 * }
 * @phpstan-type CustomScalarConfig InputCustomScalarConfig|OutputCustomScalarConfig
 */
class CustomScalarType extends ScalarType
{
    /** @phpstan-var CustomScalarConfig */
    // @phpstan-ignore-next-line specialize type
    public array $config;

    /**
     * @param array<string, mixed> $config
     * @phpstan-param CustomScalarConfig $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    public function serialize($value)
    {
        if (isset($this->config['serialize'])) {
            return $this->config['serialize']($value);
        }

        return $value;
    }

    public function parseValue($value)
    {
        if (isset($this->config['parseValue'])) {
            return $this->config['parseValue']($value);
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if (isset($this->config['parseLiteral'])) {
            return $this->config['parseLiteral']($valueNode, $variables);
        }

        return AST::valueFromASTUntyped($valueNode, $variables);
    }

    public function assertValid(): void
    {
        parent::assertValid();

        if (isset($this->config['serialize']) && ! is_callable($this->config['serialize'])) {
            throw new InvariantViolation(
                "{$this->name} must provide \"serialize\" function. If this custom Scalar "
                . 'is also used as an input type, ensure "parseValue" and "parseLiteral" '
                . 'functions are also provided.'
            );
        }

        $parseValue = $this->config['parseValue'] ?? null;
        $parseLiteral = $this->config['parseLiteral'] ?? null;
        if ($parseValue === null && $parseLiteral === null) {
            return;
        }

        if (! is_callable($parseValue) || ! is_callable($parseLiteral)) {
            throw new InvariantViolation(
                "{$this->name} must provide both \"parseValue\" and \"parseLiteral\" functions."
            );
        }
    }
}
