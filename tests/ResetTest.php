<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use PHPUnit\Framework\TestCase;

final class ResetTest extends TestCase
{
    public function testReset(): void
    {
        $string = Type::string();
        $schema = Introspection::_schema();
        $directives = Directive::getInternalDirectives();

        Type::reset();
        Introspection::reset();
        Directive::reset();

        self::assertNotSame($string, Type::string());
        self::assertNotSame($schema, Introspection::_schema());
        self::assertNotSame($directives, Directive::getInternalDirectives());
    }
}
