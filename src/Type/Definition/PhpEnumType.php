<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\SerializationError;
use GraphQL\Utils\PhpDoc;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Utils\Utils;

/**
 * @phpstan-import-type PartialEnumValueConfig from EnumTypev
 *
 * @phpstan-type PhpEnumTypeConfig array{
 *    name?: string|null,
 *    description?: string|null,
 *    enumClass: class-string<\UnitEnum>,
 *    astNode?: EnumTypeDefinitionNode|null,
 *    extensionASTNodes?: array<int, EnumTypeExtensionNode>|null
 */
class PhpEnumType extends EnumType
{
    public const MULTIPLE_DESCRIPTIONS_DISALLOWED = 'Using more than 1 Description attribute is not supported.';
    public const MULTIPLE_DEPRECATIONS_DISALLOWED = 'Using more than 1 Deprecated attribute is not supported.';


    /**
     * @phpstan-param PhpEnumTypeConfig $config
     *
     * @throws \Exception
     * @throws \ReflectionException
     */
    public function __construct(array $config)
    {
        $enumClass = $config['enumClass'];
        $reflection = new \ReflectionEnum($enumClass);

        /**
         * @var array<string, PartialEnumValueConfig> $enumDefinitions
         */
        $enumDefinitions = [];
        foreach ($reflection->getCases() as $case) {
            $enumDefinitions[$case->name] = [
                'value' => $case->getValue(),
                'description' => $this->extractDescription($case),
                'deprecationReason' => $this->deprecationReason($case),
            ];
        }

        parent::__construct([
            'name' => $config['name'] ?? $this->baseName($enumClass),
            'values' => $enumDefinitions,
            'description' => $config['description'] ?? $this->extractDescription($reflection),
            'enumClass' => $enumClass
        ]);
    }

    public function serialize($value): string
    {
        $enumClass = $this->config['enumClass'];
        if ($value instanceof $enumClass) {
            return $value->name;
        }

        if (! is_a($value, $enumClass)) {
            try {
                $instance = $enumClass::from($value);
            } catch (\ValueError|\TypeError $_) {
                $notEnumInstanceOrValue = Utils::printSafe($value);
                throw new SerializationError("Cannot serialize value as enum: {$notEnumInstanceOrValue}, expected instance or valid value of {$enumClass}.");
            }

            return $instance->name;
        }

        $notEnum = Utils::printSafe($value);
        throw new SerializationError("Cannot serialize value as enum: {$notEnum}, expected instance of {$enumClass}.");
    }

    public function parseValue($value)
    {
        // Can happen when variable values undergo a serialization cycle before execution
        if ($value instanceof $this->config['enumClass']) {
            return $value;
        }

        return parent::parseValue($value);
    }

    /** @param class-string $class */
    protected function baseName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * @param \ReflectionClassConstant|\ReflectionClass<\UnitEnum> $reflection
     *
     * @throws \Exception
     */
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

    /** @throws \Exception */
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
