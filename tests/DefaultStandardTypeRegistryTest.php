<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Registry\DefaultStandardTypeRegistry;
use PHPUnit\Framework\TestCase;

final class DefaultStandardTypeRegistryTest extends TestCase
{
    private DefaultStandardTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DefaultStandardTypeRegistry();
    }

    public function testInitializeWithStandardTypes(): void
    {
        self::assertInstanceOf(IntType::class, $this->registry->int());
        self::assertInstanceOf(FloatType::class, $this->registry->float());
        self::assertInstanceOf(BooleanType::class, $this->registry->boolean());
        self::assertInstanceOf(StringType::class, $this->registry->string());
        self::assertInstanceOf(IDType::class, $this->registry->id());
    }

    public function testReturnSameStandardTypeInstance(): void
    {
        self::assertSame($this->registry->int(), $this->registry->int());
        self::assertSame($this->registry->float(), $this->registry->float());
        self::assertSame($this->registry->boolean(), $this->registry->boolean());
        self::assertSame($this->registry->string(), $this->registry->string());
        self::assertSame($this->registry->id(), $this->registry->id());
    }

    public function testAllowOverridingStandardType(): void
    {
        $this->registry = new DefaultStandardTypeRegistry(CustomIntType::class);

        self::assertInstanceOf(CustomIntType::class, $this->registry->int());
        self::assertInstanceOf(FloatType::class, $this->registry->float());
        self::assertInstanceOf(BooleanType::class, $this->registry->boolean());
        self::assertInstanceOf(StringType::class, $this->registry->string());
        self::assertInstanceOf(IDType::class, $this->registry->id());
    }
}
