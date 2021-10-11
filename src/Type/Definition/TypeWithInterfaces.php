<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;

use function is_array;
use function is_callable;

/**
 * @see ImplementingType
 */
trait TypeWithInterfaces
{
    /**
     * Lazily initialized.
     *
     * @var array<int, InterfaceType>
     */
    private array $interfaces;

    public function implementsInterface(InterfaceType $interfaceType): bool
    {
        if (! isset($this->interfaces)) {
            $this->initializeInterfaces();
        }

        foreach ($this->interfaces as $interface) {
            if ($interfaceType->name === $interface->name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, InterfaceType>
     */
    public function getInterfaces(): array
    {
        if (! isset($this->interfaces)) {
            $this->initializeInterfaces();
        }

        return $this->interfaces;
    }

    private function initializeInterfaces(): void
    {
        $this->interfaces = [];

        if (! isset($this->config['interfaces'])) {
            return;
        }

        $interfaces = $this->config['interfaces'];
        if (is_callable($interfaces)) {
            $interfaces = $interfaces();
        }

        if (! is_array($interfaces)) {
            throw new InvariantViolation(
                "{$this->name} interfaces must be an Array or a callable which returns an Array."
            );
        }

        foreach ($interfaces as $interface) {
            /** @var Type&InterfaceType $resolvedInterface */
            $resolvedInterface  = Schema::resolveType($interface);
            $this->interfaces[] = $resolvedInterface;
        }
    }
}
