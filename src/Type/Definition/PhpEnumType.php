<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\SerializationError;
use GraphQL\Utils\PhpDoc;
use GraphQL\Utils\Utils;

/**
 * @phpstan-import-type PartialEnumValueConfig from EnumType
 */
class PhpEnumType extends EnumType
{
    public const MULTIPLE_DESCRIPTIONS_DISALLOWED = 'Using more than 1 Description attribute is not supported.';
    public const MULTIPLE_DEPRECATIONS_DISALLOWED = 'Using more than 1 Deprecated attribute is not supported.';

    /** @var class-string<\UnitEnum> */
    protected string $enumClass;

    /**
     * @param class-string<\UnitEnum> $enum
     * @param string|null $name The name the enum will have in the schema, defaults to the basename of the given class
     */
    public function __construct(string $enum, string $name = null)
    {
        $this->enumClass = $enum;
        $reflection = new \ReflectionEnum($enum);

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
            'name' => $name ?? $this->baseName($enum),
            'values' => $enumDefinitions,
            'description' => $this->extractDescription($reflection),
        ]);
    }

    public function serialize($value): string
    {
        if (! is_a($value, $this->enumClass)) {
            $notEnum = Utils::printSafe($value);
            throw new SerializationError("Cannot serialize value as enum: {$notEnum}, expected instance of {$this->enumClass}.");
        }

        return $value->name;
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
