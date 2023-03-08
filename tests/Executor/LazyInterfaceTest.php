<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class LazyInterfaceTest extends TestCase
{
    protected Schema $schema;

    protected InterfaceType $lazyInterface;

    protected ObjectType $testObject;

    public function testReturnsFragmentsWithLazyCreatedInterface(): void
    {
        $request = '
        {
            lazyInterface {
                ... on TestObject {
                    name
                }
            }
        }
        ';

        $expected = [
            'data' => [
                'lazyInterface' => [
                    'name' => 'testname',
                ],
            ],
        ];

        self::assertSame(
            $expected,
            Executor::execute($this->schema, Parser::parse($request))->toArray()
        );
    }

    protected function setUp(): void
    {
        $query = new ObjectType([
            'name' => 'query',
            'fields' => fn (): array => [
                'lazyInterface' => [
                    'type' => $this->makeLazyInterfaceType(),
                    'resolve' => static fn (): array => [],
                ],
            ],
        ]);

        $this->schema = new Schema(
            [
                'query' => $query,
                'types' => [$this->makeTestObjectType()], ]
        );
    }

    protected function makeLazyInterfaceType(): InterfaceType
    {
        return $this->lazyInterface ??= new InterfaceType([
            'name' => 'LazyInterface',
            'fields' => [
                'a' => Type::string(),
            ],
            'resolveType' => fn (): ObjectType => $this->makeTestObjectType(),
        ]);
    }

    protected function makeTestObjectType(): ObjectType
    {
        return $this->testObject ??= new ObjectType([
            'name' => 'TestObject',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'testname',
                ],
            ],
            'interfaces' => [$this->makeLazyInterfaceType()],
        ]);
    }
}
