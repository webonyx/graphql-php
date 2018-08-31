<?php
namespace GraphQL\Tests;

use GraphQL\Language\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testReturnTokenOnArray() : void
    {
        $token = new Token('Kind', 1, 10, 3, 5);
        $expected = [
            'kind' => 'Kind',
            'value' => null,
            'line' => 3,
            'column' => 5
        ];

        $this->assertEquals($expected, $token->toArray());
    }
}
