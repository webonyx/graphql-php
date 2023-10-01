<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

/**
 * @see describe('Type System: Directive', () => {
 */
final class DirectiveTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @see it('defines a directive with no args', () => { */
    public function testDefinesADirectiveWithNoArgs(): void
    {
        $name = 'Foo';
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

    /** @see it('defines a directive with multiple args' */
    public function testDefinesADirectiveWithMultipleArgs(): void
    {
        $name = 'Foo';
        $locations = [DirectiveLocation::QUERY];

        $directive = new Directive([
            'name' => $name,
            'args' => [
                'foo' => [
                    'type' => Type::string(),
                    'deprecationReason' => 'Just because',
                ],
                'bar' => [
                    'type' => Type::int(),
                ],
            ],
            'locations' => $locations,
        ]);

        self::assertSame($name, $directive->name);
        self::assertFalse($directive->isRepeatable);
        self::assertSame($locations, $directive->locations);

        $argumentFoo = $directive->args[0];
        self::assertArraySubset(
            [
                'name' => 'foo',
                'description' => null,
                'deprecationReason' => 'Just because',
                'defaultValue' => null,
                'astNode' => null,
            ],
            (array) $argumentFoo
        );
        self::assertTrue($argumentFoo->isDeprecated());

        $argumentBar = $directive->args[1];
        self::assertArraySubset(
            [
                'name' => 'bar',
                'description' => null,
                'deprecationReason' => null,
                'defaultValue' => null,
                'astNode' => null,
            ],
            (array) $argumentBar
        );
        self::assertFalse($argumentBar->isDeprecated());
    }

    // TODO implement all of https://github.com/graphql/graphql-js/blob/master/src/type/__tests__/directive-test.js
}
