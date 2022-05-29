<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use Exception;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\SerializationError;
use GraphQL\GraphQL;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Type\TestClasses\MultipleDeprecationsPhpEnum;
use GraphQL\Tests\Type\TestClasses\MultipleDescriptionsCasePhpEnum;
use GraphQL\Tests\Type\TestClasses\MultipleDescriptionsPhpEnum;
use GraphQL\Tests\Type\TestClasses\PhpEnum;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;

final class PhpEnumTypeTest extends TestCaseBase
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
"""foo"""
enum PhpEnum {
  """bar"""
  A
  B @deprecated
  C @deprecated(reason: "baz")
}
GRAPHQL,
            SchemaPrinter::printType($enumType)
        );
    }

    public function testMultipleDescriptionsDisallowed(): void
    {
        self::expectExceptionObject(new Exception(PhpEnumType::MULTIPLE_DESCRIPTIONS_DISALLOWED));
        new PhpEnumType(MultipleDescriptionsPhpEnum::class);
    }

    public function testMultipleDescriptionsDisallowedOnCase(): void
    {
        self::expectExceptionObject(new Exception(PhpEnumType::MULTIPLE_DESCRIPTIONS_DISALLOWED));
        new PhpEnumType(MultipleDescriptionsCasePhpEnum::class);
    }

    public function testMultipleDeprecationsDisallowed(): void
    {
        self::expectExceptionObject(new Exception(PhpEnumType::MULTIPLE_DEPRECATIONS_DISALLOWED));
        new PhpEnumType(MultipleDeprecationsPhpEnum::class);
    }

    public function testExecutesWithEnumTypeFromPhpEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => Type::nonNull($enumType),
                        'args' => [
                            'bar' => [
                                'type' => Type::nonNull($enumType),
                            ],
                        ],
                        'resolve' => static function ($_, array $args): PhpEnum {
                            $bar = $args['bar'];
                            assert($bar === PhpEnum::A);

                            return $bar;
                        },
                    ],
                ],
            ]),
        ]);

        self::assertSame(
            [
                'data' => [
                    'foo' => 'A',
                ],
            ],
            GraphQL::executeQuery($schema, '{ foo(bar: A) }')->toArray()
        );
    }

    public function testFailsToSerializeNonEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => Type::nonNull($enumType),
                        'resolve' => static fn (): string => 'A',
                    ],
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ foo }');

        self::expectExceptionObject(new SerializationError('Cannot serialize A, expected enum GraphQL\\Tests\\Type\\TestClasses\\PhpEnum.'));
        $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);
    }
}
