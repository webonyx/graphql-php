<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * Scalar Type Definition.
 *
 * The leaf values of any request and input values to arguments are
 * Scalars (or Enums) and are defined with a name and a series of coercion
 * functions used to ensure validity.
 *
 * Example:
 *
 * class OddType extends ScalarType
 * {
 *     public $name = 'Odd',
 *     public function serialize($value)
 *     {
 *         return $value % 2 === 1 ? $value : null;
 *     }
 * }
 *
 * @phpstan-type ScalarConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   astNode?: ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<ScalarTypeExtensionNode>|null
 * }
 */
abstract class ScalarType extends Type implements OutputType, InputType, LeafType, NullableType, NamedType
{
    use NamedTypeImplementation;

    public ?ScalarTypeDefinitionNode $astNode;

    /** @var array<ScalarTypeExtensionNode> */
    public array $extensionASTNodes;

    /** @phpstan-var ScalarConfig */
    public array $config;

    /**
     * @throws InvariantViolation
     *
     * @phpstan-param ScalarConfig $config
     */
    public function __construct(array $config = [])
    {
        $this->name = $config['name'] ?? $this->inferName();
        $this->description = $config['description'] ?? $this->description ?? null;
        $this->astNode = $config['astNode'] ?? null;
        $this->extensionASTNodes = $config['extensionASTNodes'] ?? [];

        $this->config = $config;
    }

    public function assertValid(): void
    {
        Utils::assertValidName($this->name);
    }

    public function astNode(): ?ScalarTypeDefinitionNode
    {
        return $this->astNode;
    }

    /** @return array<int, ScalarTypeExtensionNode> */
    public function extensionASTNodes(): array
    {
        return $this->extensionASTNodes;
    }
}
