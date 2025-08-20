<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\Definition;

use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testWrappingNonNullableTypeWithNonNull(): void
    {
        $nonNullableString = Type::nonNull(Type::string());

        self::assertSame($nonNullableString, Type::nonNull($nonNullableString));
    }
}
