<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testReturnTokenOnArray(): void
    {
        $token = new Token('Kind', 1, 10, 3, 5);
        $expected = [
            'kind' => 'Kind',
            'value' => null,
            'line' => 3,
            'column' => 5,
        ];

        self::assertEquals($expected, $token->toArray());
    }
}
