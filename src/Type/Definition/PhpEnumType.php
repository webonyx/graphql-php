<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use Exception;
use GraphQL\Error\SerializationError;
use GraphQL\Utils\Deprecated;
use GraphQL\Utils\Description;
use GraphQL\Utils\Utils;
use ReflectionAttribute;
use ReflectionEnum;
use UnitEnum;

/**
 * @phpstan-import-type PartialEnumValueConfig from EnumType
 */
class PhpEnumType extends EnumType
{
    public const MULTIPLE_DESCRIPTIONS_DISALLOWED = 'Using more than 1 Description attribute is not supported.';
    public const MULTIPLE_DEPRECATIONS_DISALLOWED = 'Using more than 1 Deprecated attribute is not supported.';

    /**
     * @var class-string<UnitEnum>
     */
    protected string $enumClass;

    /**
     * @param class-string<UnitEnum> $enum
     */
    public function __construct(string $enum, ?string $name = null)
    {
        $this->enumClass = $enum;
        $reflection = new ReflectionEnum($enum);

        /**
         * @var array<string, PartialEnumValueConfig> $enumDefinitions
         */
        $enumDefinitions = [];
        foreach ($reflection->getCases() as $case) {
            $enumDefinitions[$case->name] = [
                'value' => $case->getValue(),
                'description' => $this->description($case->getAttributes(Description::class)),
                'deprecationReason' => $this->deprecationReason($case->getAttributes(Deprecated::class)),
            ];
        }

        parent::__construct([
            'name' => $name ?? $this->baseName($enum),
            'values' => $enumDefinitions,
            'description' => $this->description($reflection->getAttributes(Description::class)),
        ]);
    }

    public function serialize($value): string
    {
        if (! is_a($value, $this->enumClass)) {
            $notEnum = Utils::printSafe($value);

            throw new SerializationError("Cannot serialize {$notEnum}, expected enum {$this->enumClass}.");
        }

        return $value->name;
    }

    /**
     * @param class-string $class
     */
    protected function baseName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * @param array<ReflectionAttribute<Description>> $attributes
     */
    protected function description(array $attributes): ?string
    {
        if (count($attributes) === 1) {
            return $attributes[0]->newInstance()->description;
        }

        if (count($attributes) > 1) {
            throw new Exception(self::MULTIPLE_DESCRIPTIONS_DISALLOWED);
        }

        return null;
    }

    /**
     * @param array<ReflectionAttribute<Deprecated>> $attributes
     */
    protected function deprecationReason(array $attributes): ?string
    {
        if (count($attributes) === 1) {
            return $attributes[0]->newInstance()->reason;
        }

        if (count($attributes) > 1) {
            throw new Exception(self::MULTIPLE_DEPRECATIONS_DISALLOWED);
        }

        return null;
    }
}
