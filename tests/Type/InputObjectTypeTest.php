<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class InputObjectTypeTest extends TestCase
{
    public function testConstructInputType(): void
    {
        $inputType = new InputObjectType(
            [
                'name' => 'Input',
                'fields' => [
                    [
                        'name' => 'field1',
                        'type' => Type::int(),
                    ],
                ],
            ]
        );

        self::assertArrayHasKey('field1', $inputType->getFields());
    }
}
