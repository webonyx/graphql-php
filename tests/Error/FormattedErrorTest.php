<?php declare(strict_types=1);

namespace GraphQL\Tests\Error;

use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class FormattedErrorTest extends TestCase
{
    /**
     * @param mixed $var
     *
     * @dataProvider printVar
     */
    public function testPrintVar($var, string $printed): void
    {
        self::assertSame($printed, FormattedError::printVar($var));
    }

    /**
     * @throws InvariantViolation
     *
     * @return iterable<array{mixed, string}>
     */
    public static function printVar(): iterable
    {
        yield [Type::string(), 'GraphQLType: String'];
        yield [new NodeList([]), 'instance of GraphQL\Language\AST\NodeList(0)'];
        yield [[2], 'array(1)'];
        yield ['', '(empty string)'];
        yield ["'", "'\\''"];
        yield [true, 'true'];
        yield [false, 'false'];
        yield [1, '1'];
        yield [2.3, '2.3'];
        yield [null, 'null'];
    }
}
