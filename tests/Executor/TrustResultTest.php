<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class TrustResultTest extends TestCase
{
    /** @see https://github.com/webonyx/graphql-php/issues/1493 */
    public function testTrustResultSkipsListIterableValidation(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'list' => [
                        'type' => Type::listOf(Type::string()),
                        'resolve' => static fn (): string => 'not an iterable',
                    ],
                ],
            ]),
        ]);

        $query = '{ list }';

        // Without trustResult, it should have an error in the result
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Expected field Query.list to return iterable, but got: string.', $result->errors[0]->getMessage());

        // With trustResult, it should NOT have the InvariantViolation error.
        // However, it will fail with a TypeError because of the 'iterable' type hint in completeListValue.
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('completeListValue()', $result->errors[0]->getMessage());
        $this->assertStringContainsString('must be of type', $result->errors[0]->getMessage());
    }

    public function testTrustResultSkipsLeafSerialization(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'scalar' => [
                        'type' => Type::int(),
                        'resolve' => static fn (): string => '123', // should be int, but we trust it
                    ],
                ],
            ]),
        ]);

        $query = '{ scalar }';

        // Without trustResult, it returns 123 (int) because Type::int() serializes '123' to 123
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertSame(123, $result->data['scalar']);

        // With trustResult, it should return '123' (string) directly without serialization
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        $this->assertSame('123', $result->data['scalar']);
    }

    public function testTrustResultSkipsIsTypeOfValidation(): void
    {
        $someType = new ObjectType([
            'name' => 'SomeType',
            'fields' => [
                'foo' => [
                    'type' => Type::string(),
                    'resolve' => static fn ($root) => $root['foo'] ?? null,
                ],
            ],
            'isTypeOf' => static fn ($value): bool => is_array($value) && isset($value['valid']) && $value['valid'] === true,
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'obj' => [
                        'type' => $someType,
                        'resolve' => static fn (): array => ['foo' => 'bar', 'valid' => false],
                    ],
                ],
            ]),
        ]);

        $query = '{ obj { foo } }';

        // Without trustResult, it should have an error
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Expected value of type "SomeType" but got:', $result->errors[0]->getMessage());

        // With trustResult, it should skip isTypeOf check
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        $this->assertCount(0, $result->errors);
        $this->assertSame('bar', $result->data['obj']['foo']);
    }

    public function testTrustResultSkipsNonNullValidation(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'nonNull' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => static fn (): ?string => null,
                    ],
                ],
            ]),
        ]);

        $query = '{ nonNull }';

        // Without trustResult, it should have an error
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Cannot return null for non-nullable field "Query.nonNull".', $result->errors[0]->getMessage());

        // With trustResult, it returns null without error
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        $this->assertCount(0, $result->errors);
        $this->assertNull($result->data['nonNull']);
    }
}
