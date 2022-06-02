<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use PHPUnit\Framework\TestCase;

/**
 * @see describe('Type System: Directive', () => {
 */
final class DirectiveTest extends TestCase
{
    /**
     * @see it('defines a directive with no args', () => {
     */
    public function testDefinesADirectiveWithNoArgs(): void
    {
        $name      = 'Foo';
        $locations = [DirectiveLocation::QUERY];

        $directive = new Directive([
            'name' => $name,
            'locations' => $locations,
        ]);

        self::assertSame($name, $directive->name);
        self::assertSame([], $directive->args);
        self::assertFalse($directive->isRepeatable);
        self::assertSame($locations, $directive->locations);
    }

    // TODO implement all of https://github.com/graphql/graphql-js/blob/master/src/type/__tests__/directive-test.js
}
