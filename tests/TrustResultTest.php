<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\SerializationError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Covers the `trustResult` execution option proposed in
 * https://github.com/webonyx/graphql-php/issues/1493.
 *
 * When `trustResult` is enabled the executor skips the per-leaf `serialize()`
 * validation pass, trusting that resolvers already returned correctly typed,
 * serialized values. This is the dominant cost on large payloads.
 */
class TrustResultTest extends TestCase
{
    public function testInvalidLeafValueIsRejectedWhenTrustResultDisabled(): void
    {
        $schema = self::schemaWithStrictScalar();
        $document = Parser::parse('{ value }');

        $result = Executor::execute($schema, $document, null, null, null, null, null, false);

        self::assertNotEmpty($result->errors, 'Without trustResult the invalid leaf value must surface a serialization error.');
        self::assertStringContainsString('StrictString', $result->errors[0]->getMessage());
    }

    public function testInvalidLeafValuePassesThroughWhenTrustResultEnabled(): void
    {
        $schema = self::schemaWithStrictScalar();
        $document = Parser::parse('{ value }');

        $result = Executor::execute($schema, $document, null, null, null, null, null, true);

        self::assertEmpty($result->errors, 'With trustResult the executor must not run leaf serialization validation.');
        self::assertSame(['value' => 12345], $result->data);
    }

    private static function schemaWithStrictScalar(): Schema
    {
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'value' => [
                    'type' => Type::nonNull(new StrictStringScalar()),
                    'resolve' => static fn (): mixed => 12345, // intentionally NOT a string
                ],
            ],
        ]);

        return new Schema(['query' => $query]);
    }
}

/**
 * A scalar whose serialize() throws for any non-string input. This lets us
 * prove that with trustResult disabled the invalid value is caught (error),
 * while with trustResult enabled the value passes through untouched.
 */
final class StrictStringScalar extends ScalarType
{
    public string $name = 'StrictString';

    public function serialize($value): string
    {
        if (! is_string($value)) {
            throw new SerializationError('StrictString requires a string, got ' . Utils::printSafe($value));
        }

        return $value;
    }

    public function parseValue($value): string
    {
        return $this->serialize($value);
    }

    public function parseLiteral(\GraphQL\Language\AST\Node $valueNode, ?array $variables = null): string
    {
        throw new SerializationError('StrictString cannot parse literals.');
    }
}