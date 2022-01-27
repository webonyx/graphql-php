<?php

namespace GraphQL\Tests\Type;

use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Type\TestClasses\PhpEnum;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Utils\SchemaPrinter;

class PhpEnumTypeTest extends TestCaseBase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (version_compare(phpversion(), '8.1', '<')) {
            self::markTestSkipped('Native PHP enums are only available with PHP 8.1');
        }
    }

    public function testConstructEnumTypeFromPhpEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        self::assertSame(
            <<<'GRAPHQL'
enum PhpEnum {
  A
  B
}
GRAPHQL,
            SchemaPrinter::printType($enumType)
        );
    }
}
