<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\BuiltInDefinitions;

/**
 * @see NamedType
 */
trait NamedTypeImplementation
{
    public string $name;

    public ?string $description;

    public function toString(): string
    {
        return $this->name;
    }

    /** @throws InvariantViolation */
    protected function inferName(): string
    {
        if (isset($this->name)) { // @phpstan-ignore-line property might be uninitialized
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $reflection = new \ReflectionClass($this);
        $name = $reflection->getShortName();

        if ($reflection->getNamespaceName() !== __NAMESPACE__) {
            $withoutPrefixType = preg_replace('~Type$~', '', $name);
            assert(is_string($withoutPrefixType), 'regex is statically known to be correct');

            return $withoutPrefixType;
        }

        throw new InvariantViolation('Must provide name for Type.');
    }

    public function isBuiltInType(): bool
    {
        return BuiltInDefinitions::isBuiltInType($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
