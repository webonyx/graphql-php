<?php
namespace GraphQL\Tests;

use GraphQL\Language\Token;

class TokenTest extends \PHPUnit_Framework_TestCase
{
    public function testReturnTokenOnArray()
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
