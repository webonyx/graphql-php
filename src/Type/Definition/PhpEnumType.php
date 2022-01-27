<?php

namespace GraphQL\Type\Definition;

use GraphQL\Error\SerializationError;
use GraphQL\Utils\Utils;
use UnitEnum;

/**
 * @phpstan-import-type PartialEnumValueConfig from EnumType
 */
class PhpEnumType extends EnumType
{
    /**
     * @var class-string<UnitEnum>
     */
    protected string $enumClass;

    /**
     * @param class-string<UnitEnum> $enum TODO perhaps only accept BackedEnum or StringBackedEnum?
     */
    public function __construct(string $enum, ?string $name = null)
    {
        $this->enumClass = $enum;
        $reflection = new \ReflectionEnum($enum);

        /** @var array<\UnitEnum> $cases */
        $cases = $enum::cases();

        /**
         * @var array<string, PartialEnumValueConfig> $enumDefinitions
         */
        $enumDefinitions = [];
        foreach ($cases as $case) {
            $reflectionCase = $reflection->getCase($case->name);
            $docComment = $reflectionCase->getDocComment();

            $enumDefinitions[] = [
                'name' => $case->name,
                'value' => $case,
                'description' => $this->description($docComment),
                'deprecationReason' => $this->deprecationReason($docComment),
            ]
        }

        parent::__consstruct([
            'name' => $name ?? $this->baseName($enum),
            'values' => $enumDefinitions,
            'description' => $this->description($reflection->getDocComment()),
        ])
    }

    /**
     * @param class-string $class
     */
    public function baseName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    public function serialize($value): string
    {
        if (! is_a($value, $this->enumClass, true)) {
            throw new SerializationError('Cannot serialize value as enum: ' . Utils::printSafe($value));
        }

        return $value->name;
    }
}
