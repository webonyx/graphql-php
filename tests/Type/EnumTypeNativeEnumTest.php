<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Tests\Type\PhpEnumType\StringPhpEnum;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class EnumTypeNativeEnumTest extends TestCase
{

    private Schema $schema;

    public function setUp(): void
    {
        if (version_compare(phpversion(), '8.1', '<')) {
            self::markTestSkipped('Native PHP enums are only available with PHP 8.1');
        }

        $simpleType = new EnumType([
            'name' => (new \ReflectionClass(StringPhpEnum::class))->getShortName(),
            'values' => [
                StringPhpEnum::A->value,
                StringPhpEnum::B->value,
                StringPhpEnum::C->value,
            ],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'simpleEnum' => [
                    'type' => $simpleType,
                    'args' => [
                        'fromEnum' => ['type' => $simpleType],
                    ],
                    'resolve' => static function ($rootValue, array $args) {
                        if (isset($args['fromEnum'])) {
                            return StringPhpEnum::tryFrom($args['fromEnum']);
                        }
                        return null;
                    },
                ],
            ],
        ]);

        $this->schema = new Schema([
            'query' => $QueryType,
        ]);
    }

    public function testAcceptEnumValues(): void
    {
        self::assertSame(
            ['data' => ['simpleEnum' => StringPhpEnum::A->value]],
            GraphQL::executeQuery($this->schema, '{ simpleEnum(fromEnum: Avalue) }')->toArray()
        );
    }
    public function testFailsOnNonexistentEnumValues(): void
    {
        $result = GraphQL::executeQuery($this->schema, '{ simpleEnum(fromEnum: Dvalue) }');
        self::assertCount(1, $result->errors);
        $err = 'Value "Dvalue" does not exist in "StringPhpEnum" enum. Did you mean the enum value "Avalue", "Bvalue", or "Cvalue"?';
        self::assertSame($err, $result->errors[0]->getMessage());
    }
}
