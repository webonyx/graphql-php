<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\SerializationError;
use GraphQL\Utils\PhpDoc;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * @see EnumValueDefinitionNode
 * @phpstan-import-type PartialEnumValueConfig from EnumType
 *
 * @phpstan-type PhpEnumTypeConfig array{
 *   name?: string|null,
 *   description?: string|null,
 *   enumPath: class-string<\UnitEnum>,
 *   astNode?: EnumTypeDefinitionNode|null,
 *   extensionASTNodes?: array<int, EnumTypeExtensionNode>|null
 * }
 */
class PhpEnumType extends EnumType
{
    public const MULTIPLE_DESCRIPTIONS_DISALLOWED = 'Using more than 1 Description attribute is not supported.';
    public const MULTIPLE_DEPRECATIONS_DISALLOWED = 'Using more than 1 Deprecated attribute is not supported.';

    /**
     * @phpstan-param PhpEnumTypeConfig $config
     */
    public function __construct(array $config)
    {
        $reflection = new \ReflectionEnum($config['enumPath']);

        $enumDefinitions = [];
        foreach ($reflection->getCases() as $case) {
            $enumDefinitions[$case->name] = [
                'value' => $case->getValue(),
                'description' => $this->extractDescription($case),
                'deprecationReason' => $this->deprecationReason($case),
            ];
        }

        parent::__construct([
            'name' => $config['name'] ?? $this->baseName($config['enumPath']),
            'values' => $enumDefinitions,
            'description' => $config['description'] ?? $this->extractDescription($reflection),
            'enumPath' => $config['enumPath']
        ]);
    }

    public function serialize($value): string
    {
        if (! is_a($value, $this->config['enumPath'])) {
            $notEnum = Utils::printSafe($value);
            throw new SerializationError("Cannot serialize value as enum: {$notEnum}, expected instance of {$this->config['enumPath']}.");
        }
        return $value->name;
    }

    /**
    /* @param class-string $path
     */
    public static function fromPath($path): PhpEnumType {
        return new static(["enumPath"=>$path]);
    }

    /** @param class-string $class */
    protected function baseName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    protected function extractDescription(\ReflectionClassConstant|\ReflectionClass $reflection): ?string
    {
        $attributes = $reflection->getAttributes(Description::class);

        if (count($attributes) === 1) {
            return $attributes[0]->newInstance()->description;
        }

        if (count($attributes) > 1) {
            throw new \Exception(self::MULTIPLE_DESCRIPTIONS_DISALLOWED);
        }

        $comment = $reflection->getDocComment();
        $unpadded = PhpDoc::unpad($comment);

        return PhpDoc::unwrap($unpadded);
    }

    protected function deprecationReason(\ReflectionClassConstant $reflection): ?string
    {
        $attributes = $reflection->getAttributes(Deprecated::class);

        if (count($attributes) === 1) {
            return $attributes[0]->newInstance()->reason;
        }

        if (count($attributes) > 1) {
            throw new \Exception(self::MULTIPLE_DEPRECATIONS_DISALLOWED);
        }

        return null;
    }
}
