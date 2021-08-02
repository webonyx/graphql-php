<?php

declare(strict_types=1);

namespace GraphQL\Tests\Error;

use GraphQL\Error\FormattedError;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

class FormattedErrorTest extends TestCase
{
    /**
     * @dataProvider printVar
     */
    public function testPrintVar($var, string $printed): void
    {
        self::assertSame($printed, FormattedError::printVar($var));
    }

    /**
     * @return array<int, array{mixed, string}>
     */
    public function printVar(): array
    {
        return [
            [Type::string(), 'GraphQLType: String'],
            [
                new NodeList([]),
                'instance of GraphQL\Language\AST\NodeList(0)'
            ],
            [[2], 'array(1)'],
            ['', '(empty string)'],
            ["'", "'\\''"],
            [true, 'true'],
            [false, 'false'],
            [1, '1'],
            [2.3, '2.3'],
            [null, 'null'],
        ];
    }
}
